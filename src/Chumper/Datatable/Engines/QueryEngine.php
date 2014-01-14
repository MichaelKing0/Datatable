<?php namespace Chumper\Datatable\Engines;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class QueryEngine extends BaseEngine {

    /**
     * @var Builder
     */
    public $builder;
    /**
     * @var Builder
     */
    public $originalBuilder;

    /**
     * @var array single column searches
     */
    public $columnSearches = array();

    /**
     * @var Collection the returning collection
     */
    private $resultCollection;

    /**
     * @var Collection the resulting collection
     */
    private $collection = null;

    /**
     * @var array Different options
     */
    private $options = array(
        'searchOperator'    =>  'LIKE',
        'searchWithAlias'   =>  false,
        'orderOrder'        =>  null,
        'counter'           =>  0,
    );

    function __construct($builder)
    {
        parent::__construct();
        if($builder instanceof Relation)
        {
            $this->builder = $builder->getBaseQuery();
            $this->originalBuilder = clone $builder->getBaseQuery();
        }
        else
        {
            $this->builder = $builder;
            $this->originalBuilder = clone $builder;
        }
    }

    public function order($column, $oder = BaseEngine::ORDER_ASC)
    {
        $this->orderColumn = $column;
        $this->orderDirection = $oder;
    }

    public function search($value)
    {
        $this->search = $value;
    }

    public function searchOnColumn($columnName, $value)
    {
        $this->columnSearches[$columnName] = $value;
    }

    public function skip($value)
    {
        $this->skip = $value;
    }

    public function take($value)
    {
        $this->limit = $value;
    }

    public function count()
    {
        return $this->options['counter'];
    }

    public function totalCount()
    {
        return $this->originalBuilder->count();
    }

    public function getArray()
    {
       return $this->getCollection($this->builder)->toArray();
    }

    public function reset()
    {
        $this->builder = $this->originalBuilder;
    }


    public function setSearchOperator($value = "LIKE")
    {
        $this->options['searchOperator'] = $value;
    }

    public function setSearchWithAlias()
    {
        $this->options['searchWithAlias'] = true;
        return $this;
    }

    //--------PRIVATE FUNCTIONS

    protected function internalMake(Collection $columns, array $searchColumns = array())
    {
        $builder = clone $this->builder;
        $countBuilder = clone $this->builder;

        $builder = $this->doInternalSearch($builder, $searchColumns);
        $countBuilder = $this->doInternalSearch($countBuilder, $searchColumns);

        if($this->options['searchWithAlias'])
        {
            $this->options['counter'] = count($countBuilder->get());
        }
        else
        {
            $this->options['counter'] = $countBuilder->count();
        }

        $builder = $this->doInternalOrder($builder, $columns);
        $collection = $this->compile($builder, $columns);

        return $collection;
    }

    /**
     * @param $builder
     * @return Collection
     */
    private function getCollection($builder)
    {
        if($this->collection == null)
        {
            if($this->skip > 0)
            {
                $builder = $builder->skip($this->skip);
            }
            if($this->limit > 0)
            {
                $builder = $builder->take($this->limit);
            }
            //dd($this->builder->toSql());
            $this->collection = $builder->get();

            if(is_array($this->collection))
                $this->collection = new Collection($this->collection);
        }
        return $this->collection;
    }

    private function doInternalSearch($builder, $columns)
    {
        if (!empty($this->search)) {
            $this->buildSearchQuery($builder, $columns);
        }

        if (!empty($this->columnSearches)) {
            $this->buildSingleColumnSearches($builder);
        }

        return $builder;
    }
    private function buildSearchQuery($builder, $columns)
    {
        $like = $this->options['searchOperator'];
        $search = $this->search;
        $builder = $builder->where(function($query) use ($columns, $search, $like) {
            foreach ($columns as $c) {
                //column to CAST following the pattern column:newType:[maxlength]
                if(strrpos($c, ':')){
                    $c = explode(':', $c);
                    if(isset($c[2]))
                        $c[1] .= "($c[2])";
                    $query->orWhereRaw("cast($c[0] as $c[1]) ".$like." ?", array("%$search%"));
                }
                else
                    $query->orWhere($c,$like,'%'.$search.'%');
            }
        });
        return $builder;
    }

    private function buildSingleColumnSearches($builder)
    {
        foreach ($this->columnSearches as $columnName => $searchValue) {
            $builder->where($columnName, $this->options['searchOperator'], '%' . $searchValue . '%');
        }
    }

    private function compile($builder, $columns)
    {
        $this->resultCollection = $this->getCollection($builder);

        $this->resultCollection = $this->resultCollection->map(function($row) use ($columns) {
            $entry = array();
            foreach ($columns as $col)
            {
                $entry[] =  $col->run($row);
            }
            return $entry;
        });
        return $this->resultCollection;
    }

    private function doInternalOrder($builder, $columns)
    {
        $i = 0;
        foreach($columns as $col)
        {
            if($i === $this->orderColumn)
            {
                $builder = $builder->orderBy($col->getName(), $this->orderDirection);
                return $builder;
            }
            $i++;
        }
        return $builder;
    }
}

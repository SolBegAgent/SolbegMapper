<?php

namespace Bicycle\Mapper;

/**
 * WithScenarioTrait adds scenario property in your mapper.
 * It may be useful if your mapper has different behavior depending on any situations.
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
trait WithScenarioTrait
{
    /**
     * @var string|null
     */
    private $scenario = null;

    /**
     * @param string|null $scenario
     * @param \Illuminate\Database\Eloquent\Model|null $model
     */
    public function __construct($scenario = null, $model = null)
    {
        $this->scenario = $scenario;
        parent::__construct($model);
    }

    /**
     * @return string
     */
    public function getScenario()
    {
        return $this->scenario;
    }

    /**
     * @param string $scenario
     */
    public function setScenario($scenario)
    {
        $this->scenario = $scenario;
        $this->refreshPreparedData();
    }
}

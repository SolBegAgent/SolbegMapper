<?php

namespace Bicycle\Mapper;

/**
 * SimpleMapper is useful when you do not want create new mapper class for any simple model.
 * So you may use this this. All that you need - define attributes map in constructor.
 * 
 * Example:
 * ```php
 *  protected function linksConfig()
 *  {
 *      return [
 *          'phones' => [
 *              'type' => LinkFactory::TYPE_ONE2MANY,
 *              'relation' => 'phones',
 *              'mapperClass' => SimpleMapper::class,
 *              'mapperParams' => [
 *                  'modelClass' => \App\Models\Phone::class,
 *                  'attributes' => [
 *                      'value',
 *                  ],
 *              ],
 *          ],
 * 
 * 
 *          // or through factory static helpers:
 *          'phones' => LinkFactory::one2manySimple('phones', \App\Models\Phone::class, [
*               'value',
*           ]),
 *      ];
 *  }
 * ```
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
class SimpleMapper extends AbstractMapper
{
    /**
     * @var array attributes map for this mapper
     */
    private $attributesConfig;

    /**
     * @var string the class name of model that is associated with this mapper.
     */
    private $modelClassConfig;

    /**
     * @var array config of links
     */
    private $linksConfig;

    /**
     * @var array the mapper's accessors
     */
    private $accessorsConfig;

    /**
     * @var array the mapper's mutators
     */
    private $mutatorsConfig;

    /**
     * @param string $modelClass the class name of model that is associated with this mapper.
     * @param array $attributes attributes map for this mapper
     * @param array $links config of links
     * @param array $accessors the mapper's accessors
     * @param array $mutators the mapper's mutators
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @inheritdoc
     */
    public function __construct($modelClass, array $attributes, array $links = [], array $accessors = [], array $mutators = [], $model = null)
    {
        $this->modelClassConfig = $modelClass;
        $this->attributesConfig = $attributes;
        $this->linksConfig = $links;
        $this->accessorsConfig = $accessors;
        $this->mutatorsConfig = $mutators;
        parent::__construct($model);
    }

    /**
     * @inheritdoc
     */
    protected function attributesMap()
    {
        return $this->attributesConfig;
    }

    /**
     * @inheritdoc
     */
    protected function modelClass()
    {
        return $this->modelClassConfig;
    }

    /**
     * @inheritdoc
     */
    protected function linksConfig()
    {
        return $this->linksConfig;
    }

    /**
     * @inheritdoc
     */
    protected function accessors()
    {
        return $this->accessorsConfig;
    }

    /**
     * @inheritdoc
     */
    protected function mutators()
    {
        return $this->mutatorsConfig;
    }
}

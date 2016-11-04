<?php

namespace Bicycle\Mapper\Links;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Arr;

/**
 * One2Many link used when model of your wrapper has many another submodels which data you want to modify jointly.
 * 
 * For example, you are writing mapper for user model. And you have `addresses` table with `user_id` column.
 * You may add/edit/delete user's addresses directly through user mapper.
 * 
 * This link type supports two variations:
 * 1. The `addresses` has `user_id` column. And you have appropriate `hasMany` eloquent relation in user model.
 * 2. You have the third table (e.g. `user_address`) that links this two tables. And you have appropriate `belongsToMany` eloquent relation.
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
class One2Many extends AbstractLink
{
    /**
     * @var string the name of linked mappers class
     */
    protected $mapperClass;

    /**
     * @var array additional params for creating linked mapper object.
     * This params will be passed in DI resolver on creating mapper.
     */
    protected $mapperParams = [];

    /**
     * @var string the name of relation that should be used on owner's model
     * for retreiving and attaching linked model.
     */
    protected $relation;

    /**
     * You may define it for keeping order of values in integer position column.
     * @var string|null the name of position attribute
     */
    protected $positionAttribute;

    /**
     * @var boolean whether need to delete manually all linked models when it was deleted from owner's model.
     */
    protected $processDeletion = true;

    /**
     * @var \App\Mappers\Base\AbstractMapper[]|null
     */
    private $values;

    /**
     * @var \App\Mappers\Base\AbstractMapper[]
     */
    private $deletedValues = [];

    /**
     * @param string $mapperClass additional params for creating linked mapper object.
     * This params will be passed in DI resolver on creating mapper.
     * @param string $relation the name of relation that should be used on owner's model
     * for retreiving and attaching linked model.
     * @param array $mapperParams additional params for creating linked mapper object.
     * This params will be passed in DI resolver on creating mapper.
     * @param boolean $processDeletion whether need to delete manually all linked models when it was deleted from owner's model.
     * @param string|null $positionAttribute the name of position attribute. You may define it for keeping order of values in integer position column.
     */
    public function __construct($mapperClass, $relation, array $mapperParams = [], $processDeletion = true, $positionAttribute = null)
    {
        $this->mapperClass = $mapperClass;
        $this->mapperParams = $mapperParams;
        $this->relation = $relation;
        $this->processDeletion = $processDeletion;
        $this->positionAttribute = $positionAttribute;
    }

    /**
     * @return Relations\BelongsToMany|Relations\HasMany
     * @throws \LogicException
     */
    protected function fetchOwnerRelation()
    {
        $model = $this->getOwner()->getModel();
        $relationName = $this->relation;

        if (!method_exists($model, $relationName))
        {
            throw new \LogicException("The `$relationName()` method is not defined in \"" . get_class($model) . '" model.');
        }

        $relation = $model->{$relationName}();
        if (!$relation instanceof Relations\BelongsToMany && !$relation instanceof Relations\HasMany)
        {
            throw new \LogicException(implode(' ', [
                "Unexpected value of the '$relationName()' relation on the \"" . get_class($model) . '" model.',
                'The "' . static::class . '" linker works only with `belongsToMany` or `hasMany` relation types.',
            ]));
        }
        return $relation;
    }

    /**
     * @inheritdoc
     * @return \App\Mappers\Base\AbstractMapper[] indexed by model keys.
     */
    public function getValue($attribute = null)
    {
        if ($this->values === null)
        {
            $ownerModel = $this->getOwner()->getModel();
            $models = $ownerModel->exists ? $ownerModel->{$this->relation} : [];

            if ($models instanceof Collection)
            {
                $models = $models->getDictionary();
            }
            elseif (!is_array($models))
            {
                throw new \UnexpectedValueException(implode(' ', [
                    "Unexpected value was returned by '$this->relation' relation of \"" . get_class($ownerModel) . '" model.',
                    'Note that the "' . static::class . '" linker works only with `hasMany` and `belongsToMany` relations.',
                ]));
            }

            $this->values = array_map([$this, 'createMapper'], $models);
        }

        return Arr::get($this->values, $attribute);
    }

    /**
     * @inheritdoc
     */
    public function setValue($value, $attribute = null)
    {
        $isEmptyValue = $value === null || $value === '';

        if ($attribute === null)
        {
            $this->setAllValues($isEmptyValue ? [] : $value);
        }
        elseif ($isEmptyValue)
        {
            $this->unsetValueByKey($attribute);
        }
        else
        {
            $this->setValueByKey($attribute, $value);
        }
    }

    /**
     * @param string $key
     * @param array $values
     */
    protected function setValueByKey($key, $values)
    {
        $resultMappers = $this->getValue();
        if (isset($resultMappers[$key]))
        {
            $mapper = $resultMappers[$key];
        }
        elseif (isset($this->deletedValues[$key]))
        {
            $mapper = $this->deletedValues[$key];
            unset($this->deletedValues[$key]);
        }
        else
        {
            $resultMappers[$key] = $mapper = $this->createMapper();
        }

        $mapper->setMappedAttributes($values);
        $this->values = $resultMappers;
    }

    /**
     * @param string $key
     */
    protected function unsetValueByKey($key)
    {
        $resultMappers = $this->getValue();
        if (isset($resultMappers[$key]) && $resultMappers[$key]->getModel()->exists)
        {
            $this->deletedValues[$key] = $resultMappers[$key];
        }
        unset($resultMappers[$key]);
        $this->values = $resultMappers;
    }

    /**
     * @param array $values
     */
    protected function setAllValues($values)
    {
        $existMappers = array_replace($this->deletedValues, $this->getValue());
        $resultMappers = [];

        foreach ($values as $key => $attributes)
        {
            if (isset($existMappers[$key]))
            {
                $mapper = $existMappers[$key];
                unset($existMappers[$key]);
            }
            else
            {
                $mapper = $this->createMapper();
            }

            $mapper->setMappedAttributes($attributes);
            $resultMappers[$key] = $mapper;
        }

        foreach ($existMappers as $key => $mapper)
        {
            if (!$mapper->getModel()->exists)
            {
                unset($existMappers[$key]);
            }
        }

        $this->values = $resultMappers;
        $this->deletedValues = $existMappers;
    }

    /**
     * @inheritdoc
     */
    protected function saveInternal($calledBeforeOwnerSaving)
    {
        if ($calledBeforeOwnerSaving || $this->values === null)
        {
            return true;
        }

        $modelRelation = $this->fetchOwnerRelation();
        if ($modelRelation instanceof Relations\BelongsToMany)
        {
            $result = $this->saveBelongsToManyModels($modelRelation);
        }
        elseif ($modelRelation instanceof Relations\HasMany)
        {
            $result = $this->saveHasManyModels($modelRelation);
        }

        $this->resetOwnerRelation($this->relation);
        $this->values = null;
        $this->deletedValues = [];
        return $result;
    }

    /**
     * @param Relations\BelongsToMany $relation
     * @return boolean
     */
    protected function saveBelongsToManyModels($relation)
    {
        $result = true;
        $newIds = [];
        foreach ($this->getValue() as $mapper)
        {
            $result &= $saved = $mapper->save();
            if ($saved)
            {
                $newIds[] = $mapper->getModel()->getKey();
            }
        }
        $relation->sync($newIds);

        if ($this->processDeletion)
        {
            foreach ($this->deletedValues as $mapper)
            {
                $result &= $mapper->delete(false);
            }
        }

        return (bool) $result;
    }

    /**
     * @param Relations\HasMany $relation
     * @return boolean
     */
    protected function saveHasManyModels($relation)
    {
        $result = true;
        $ownerModelKey = $relation->getParentKey();
        $modelForeignKey = $relation->getPlainForeignKey();

        foreach ($this->deletedValues as $mapper)
        {
            if ($this->processDeletion)
            {
                $result &= $mapper->delete(false);
            }
            else
            {
                $mapper->getModel()->setAttribute($modelForeignKey, null);
                $result &= $mapper->save(false);
            }
        }

        foreach ($this->getValue() as $mapper)
        {
            $mapper->getModel()->setAttribute($modelForeignKey, $ownerModelKey);
            $result &= $mapper->save(false);
        }

        return (bool) $result;
    }

    /**
     * @inheritdoc
     */
    protected function deleteInternal($calledBeforeOwnerDeleting)
    {
        if (!$calledBeforeOwnerDeleting || !$this->processDeletion)
        {
            return true;
        }

        $result = true;
        $modelRelation = $this->fetchOwnerRelation();
        if ($modelRelation instanceof Relations\BelongsToMany)
        {
            $modelRelation->sync([]);
        }

        $mappers = array_replace($this->deletedValues, $this->getValue());
        /* @var $mappers \App\Mappers\Base\AbstractMapper[] */
        foreach ($mappers as $mapper)
        {
            $result &= $mapper->delete(false) !== false;
        }

        $this->resetOwnerRelation($this->relation);
        $this->values = null;
        $this->deletedValues = [];
        return (bool) $result;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @return \App\Mappers\Base\AbstractMapper
     */
    protected function createMapper($model = null)
    {
        return $this->getContainer()->make($this->mapperClass, array_merge($this->mapperParams, [
            'model' => $model,
        ]));
    }
}

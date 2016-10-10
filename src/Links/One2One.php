<?php

namespace Bicycle\Mapper\Links;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations;

/**
 * One2One link links two associated models.
 * 
 * For example, you are writing mapper for user model. And you have `users` and `personal_data` table.
 * One user has one and only one record in `personal_data`.
 * 
 * This link type supports two variations:
 * 1. The `personal_data` has `user_id` column. And you have appropriate `hasOne` or `hasMany` eloquent relation in user model.
 * 2. You have the third table (e.g. `user_personal_data`) that links this two tables. And you have appropriate `belongsToMany` eloquent relation.
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
class One2One extends AbstractLink
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
     * for retreiving linked model.
     */
    protected $retreiveRelation;

    /**
     * @var string the name of relation that should be used on owner's model
     * for attaching linked model.
     */
    protected $attachRelation;

    /**
     * @var boolean whether need to delete linked model after deleting owner's model.
     */
    protected $processDeletion = true;

    /**
     * @var boolean whether linked model should be deleted when value was unset.
     */
    protected $deleteOnUnset = true;

    /**
     * @var \Bicycle\Mapper\AbstractMapper|null|boolean
     */
    private $oldMapper = false;

    /**
     * @var \Bicycle\Mapper\AbstractMapper|null|boolean
     */
    private $value = false;

    /**
     * @param string $mapperClass the name of linked mappers class
     * @param string $retreiveRelation the name of relation that should be used on owner's model
     * for retreiving linked model.
     * @param string|null $attachRelation the name of relation that should be used on owner's model
     * for attaching linked model. If null then $retreiveRelation will be used instead of.
     * @param array $mapperParams additional params for creating linked mapper object.
     * This params will be passed in DI resolver on creating mapper.
     * @param boolean $processDeletion whether need to delete linked model after deleting owner's model.
     */
    public function __construct($mapperClass, $retreiveRelation, $attachRelation = null, array $mapperParams = [], $processDeletion = false)
    {
        $this->mapperClass = $mapperClass;
        $this->mapperParams = $mapperParams;
        $this->retreiveRelation = $retreiveRelation;
        $this->attachRelation = $attachRelation === null ? $retreiveRelation : $attachRelation;
        $this->processDeletion = $processDeletion;
    }

    /**
     * @return \Bicycle\Mapper\AbstractMapper|null
     */
    protected function getOldMapper()
    {
        if ($this->oldMapper !== false)
        {
            return $this->oldMapper;
        }

        $model = $this->getOwner()->getModel()->exists
            ? $this->getOwner()->getModel()->{$this->retreiveRelation}
            : null;

        if (is_array($model))
        {
            $model = Arr::first($model);
        }
        elseif ($model instanceof Collection)
        {
            $model = $model->first();
        }

        return $this->oldMapper = $model ? $this->createMapper($model) : null;
    }

    /**
     * @inheritdoc
     * @return \Bicycle\Mapper\AbstractMapper|null
     */
    public function getValue($attribute = null)
    {
        if ($this->value === false)
        {
            $this->value = $this->getOldMapper();
        }

        return ($this->value === null || $attribute === null)
            ? $this->value
            : $this->value->fetchMappedValue($attribute);
    }

    /**
     * @inheritdoc
     */
    public function setValue($value, $attribute = null)
    {
        if ($attribute === null && ($value === null || $value === ''))
        {
            $this->value = null;
            return;
        }

        $this->value = $mapper = ($this->getValue() ?: $this->getOldMapper()) ?: $this->createMapper();
        if ($attribute !== null)
        {
            $mapper->populateMappedValue($attribute, $value);
            return;
        }

        $mapper->setMappedAttributes($value);
    }

    /**
     * @inheritdoc
     */
    protected function saveInternal($calledBeforeOwnerSaving)
    {
        if ($calledBeforeOwnerSaving || $this->value === false)
        {
            return true;
        }
        elseif ($this->value === null)
        {
            $oldMapper = $this->getOldMapper();
            $result = $oldMapper ? $this->detachLinkedMapper($oldMapper) : true;
        }
        else
        {
            $result = $this->attachLinkedMapper($this->value);
        }

        $this->resetOwnerRelation($this->retreiveRelation);
        $this->oldMapper = $this->value;
        return $result;
    }

    /**
     * Attaches linked to the owner.
     * @param \Bicycle\Mapper\AbstractMapper $mapper
     * @return boolean whether the attaching proccess has been successfully done
     * @throws \LogicException
     */
    protected function attachLinkedMapper($mapper)
    {
        $isNewRecord = !$mapper->getModel()->exists;
        if (!$mapper->save(false))
        {
            return false;
        }
        elseif (!$isNewRecord)
        {
            return true;
        }

        $relation = $this->getOwner()->getModel()->{$this->attachRelation}();
        if ($relation instanceof Relations\BelongsToMany)
        {
            $relation->attach($mapper->getModel());
            return true; // bad solution in laravel: the `attach()` method does not return value
        }
        elseif ($relation instanceof Relations\HasOneOrMany)
        {
            return (bool) $relation->save($mapper->getModel());
        }

        throw new \LogicException(implode(' ', [
            "Unexpected value of the '{$this->attachRelation}()' relation on the \"" . get_class($this->getOwner()->getModel()) . '" model.',
            'The "' . static::class . '" linker works only with either `belongsToMany` or `hasOne` or `hasMany` relation types.',
        ]));
    }

    /**
     * Detaches linked mapper from the owner.
     * @param \Bicycle\Mapper\AbstractMapper $mapper
     * @return boolean whether the dettaching proccess has been successfully done
     * @throws \LogicException
     */
    protected function detachLinkedMapper($mapper)
    {
        $relation = $this->getOwner()->getModel()->{$this->attachRelation}();

        if ($relation instanceof Relations\BelongsToMany)
        {
            $result = $relation->detach($mapper->getModel());
            return $this->deleteOnUnset ? (bool) $mapper->delete(false) : (bool) $result;
        }

        if (!$relation instanceof Relations\HasOneOrMany)
        {
            throw new \LogicException(implode(' ', [
                "Unexpected value of the '{$this->attachRelation}()' relation on the \"" . get_class($this->getOwner()->getModel()) . '" model.',
                'The "' . static::class . '" linker works only with either `belongsToMany` or `hasOne` or `hasMany` relation types.',
            ]));
        }
        elseif ($this->deleteOnUnset)
        {
            return (bool) $mapper->delete(false);
        }
        else
        {
            $mapper->getModel()->setAttribute($relation->getPlainForeignKey(), null);
            return (bool) $mapper->save(false);
        }
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

        $oldMapper = $this->getOldMapper();
        $result = $oldMapper ? $this->detachLinkedMapper($oldMapper) : true;
        $this->value = $this->oldMapper = null;
        return $result;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @return \Bicycle\Mapper\AbstractMapper
     */
    protected function createMapper($model = null)
    {
        return $this->getContainer()->make($this->mapperClass, array_merge($this->mapperParams, [
            'model' => $model,
        ]));
    }
}

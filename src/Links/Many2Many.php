<?php

namespace Bicycle\Mapper\Links;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations;

/**
 * Many2Many link used when model of your mapper associated as many-to-many with another entities.
 * 
 * For example, you have `users`, `team` and `users_team` tables.
 * And you have to edit team IDs that associated with user when you edit this user.
 * In this case you may use this link type. So your link will return and will may to populate array of IDs.
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
class Many2Many extends AbstractLink
{
    /**
     * @var string the name of relation that should be used on owner's model
     * for retreiving and attaching linked ids.
     */
    protected $relation;

    /**
     * You may define it for keeping order of values in integer position column.
     * @var string|null the name of position attribute
     */
    protected $positionAttribute;

    /**
     * @var boolean whether need to delete manually all linked ids before deleting owner's model.
     */
    protected $processDeletion = false;

    /**
     * @var string|null
     */
    private $modelClass;

    /**
     * @var array|null
     */
    private $keys;

    /**
     * @var \Illuminate\Database\Eloquent\Model[]|null
     */
    private $models;

    /**
     * @param string $relation the name of relation that should be used on owner's model
     * for retreiving and attaching linked ids.
     * @param string|null $positionAttribute the name of position attribute. You may define it for keeping order of values in integer position column.
     * @param boolean $processDeletion whether need to delete manually all linked ids before deleting owner's model.
     */
    public function __construct($relation, $positionAttribute = null, $processDeletion = false)
    {
        $this->relation = $relation;
        $this->positionAttribute = $positionAttribute;
        $this->processDeletion = $processDeletion;
    }

    /**
     * Populates models and keys properties by data from relation.
     */
    private function populateFromRelation()
    {
        $ownerModel = $this->getOwner()->getModel();
        $collection = $ownerModel->{$this->relation};
        if (!$collection instanceof Collection)
        {
            throw new \UnexpectedValueException("Unexpected value was returned by '$this->relation' of \"" . get_class($ownerModel) . '" model, may be this relation has non `belongsToMany` type.');
        }

        $this->keys = $collection->modelKeys();
        $this->models = $collection->all();
    }

    /**
     * @return Relations\BelongsToMany
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
        if (!$relation instanceof Relations\BelongsToMany)
        {
            throw new \LogicException(implode(' ', [
                "Unexpected value of the '$relationName()' relation on the \"" . get_class($model) . '" model.',
                'The "' . static::class . '" linker works only with `belongsToMany` relation type.',
            ]));
        }
        return $relation;
    }

    /**
     * @return string
     * @throws \LogicException
     */
    protected function getModelClass()
    {
        if ($this->modelClass === null)
        {
            $this->modelClass = get_class($this->fetchOwnerRelation()->getQuery()->getModel());
        }
        return $this->modelClass;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model[]
     */
    public function getModels()
    {
        if ($this->models !== null)
        {
            return $this->models;
        }
        elseif ($this->keys === null)
        {
            $this->populateFromRelation();
            return $this->models;
        }
        elseif (!$this->keys)
        {
            return $this->models = [];
        }

        $modelClass = $this->getModelClass();
        $models = $modelClass::findMany($this->keys);
        /* @var $models Collection */

        $indexedKeys = array_fill_keys($this->keys, false);
        return $this->models = array_filter(array_replace($indexedKeys, $models->getDictionary()));
    }

    /**
     * @inheritdoc
     * @return integer[]
     */
    public function getValue($attribute = null)
    {
        if ($this->keys !== null)
        {
            $values = $this->keys;
        }
        else
        {
            $this->populateFromRelation();
            $values = $this->keys;
        }

        return Arr::get($values, $attribute);
    }

    /**
     * @inheritdoc
     */
    public function setValue($value, $attribute = null)
    {
        if ($attribute === null)
        {
            $this->keys = collect($value)->all();
        }
        else
        {
            $this->keys[$attribute] = $value;
        }
        $this->models = null;
    }

    /**
     * @inheritdoc
     */
    protected function saveInternal($calledBeforeOwnerSaving)
    {
        if ($calledBeforeOwnerSaving || $this->keys === null)
        {
            return true;
        }

        $relation = $this->fetchOwnerRelation();
        $relation->sync($this->keys);

        $this->resetOwnerRelation($this->relation);
        $this->keys = $this->models = null;
        $result = true;

        if ($this->positionAttribute !== null)
        {
            $position = 0;
            foreach ($this->getOwner()->getModel()->{$this->relation} as $model)
            {
                $model->pivot->{$this->positionAttribute} = ++$position;
                $result &= $model->pivot->save();
            }
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

        $relation = $this->fetchOwnerRelation();
        $relation->sync([]);
        $this->keys = $this->models = null;

        return true;
    }
}

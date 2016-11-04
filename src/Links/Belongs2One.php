<?php

namespace Solbeg\Mapper\Links;

use Illuminate\Database\Eloquent\Relations;

/**
 * Belongs2One link links two associated models.
 * But unlike one2one type, belongs2one link should be used when model of your mapper depends on linked model.
 * 
 * For example, you are writing mapper for PersonalData model. And you have `personal_data` and `users` and table.
 * For creating personal data model you must to have already existing user record.
 * 
 * This link works only with eloquent `belongsTo` relations.
 * So your `personal_data` table should have `user_id` column and appropriate `belongsTo` relation to user model class.
 * 
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
class Belongs2One extends AbstractLink
{
    /**
     * @var string the name of linked mapper class
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
     * @var boolean whether need to delete linked model after deleting owner's model.
     */
    protected $processDeletion = true;

    /**
     * @var \Bicycle\Mapper\AbstractMapper|null
     */
    private $value;

    /**
     * @var string|null
     */
    private $_relationForeignKey;

    /**
     * @param string $mapperClass the name of linked mapper class
     * @param string $relation the name of relation that should be used on owner's model
     * for retreiving and attaching linked model.
     * @param array $mapperParams additional params for creating linked mapper object.
     * This params will be passed in DI resolver on creating mapper.
     * @param boolean $processDeletion whether need to delete linked model after deleting owner's model.
     */
    public function __construct($mapperClass, $relation, array $mapperParams = [], $processDeletion = false)
    {
        $this->mapperClass = $mapperClass;
        $this->mapperParams = $mapperParams;
        $this->relation = $relation;
        $this->processDeletion = $processDeletion;
    }

    /**
     * @return Relations\BelongsTo
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
        if (!$relation instanceof Relations\BelongsTo)
        {
            throw new \LogicException(implode(' ', [
                "Unexpected value of the '$relationName()' relation on the \"" . get_class($model) . '" model.',
                'The "' . static::class . '" linker works only with `belongsTo` relation type.',
            ]));
        }
        return $relation;
    }

    /**
     * @return string the name of foreign key of relation.
     */
    protected function getRelationForeignKey()
    {
        if ($this->_relationForeignKey === null)
        {
            $this->_relationForeignKey = $this->fetchOwnerRelation()->getForeignKey();
        }
        return $this->_relationForeignKey;
    }

    /**
     * @inheritdoc
     * @return \Bicycle\Mapper\AbstractMapper
     */
    public function getValue($attribute = null)
    {
        if ($this->value === null)
        {
            $ownerModel = $this->getOwner()->getModel();

            if ($ownerModel->{$this->getRelationForeignKey()})
            {
                $mapper = $this->createMapper($ownerModel->{$this->relation} ?: null);
            }
            else
            {
                $mapper = $this->createMapper();
                $ownerModel->setRelation($this->relation, $mapper->getModel());
            }

            $this->value = $mapper;
        }

        return $attribute === null
            ? $this->value
            : $this->value->fetchMappedValue($attribute);
    }

    /**
     * @inheritdoc
     */
    public function setValue($value, $attribute = null)
    {
        $mapper = $this->getValue();

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
        if (!$calledBeforeOwnerSaving || $this->value === null)
        {
            return true;
        }

        if (!$this->value->save(false))
        {
            return false;
        }

        $this->fetchOwnerRelation()->associate($this->value->getModel());
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function deleteInternal($calledBeforeOwnerDeleting)
    {
        if (!$this->processDeletion)
        {
            return true;
        }
        elseif ($calledBeforeOwnerDeleting)
        {
            $this->getValue(); // fetches current related model if it was not fetched before
            return true;
        }
        else
        {
            $result = $this->getValue()->delete() !== false;
            $this->value = null;
            return $result;
        }
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

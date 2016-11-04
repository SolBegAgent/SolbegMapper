<?php

namespace Solbeg\Mapper\Links;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

use Solbeg\Mapper\AbstractMapper;

/**
 * AbstractLink is the base abstract class for all link types.
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
abstract class AbstractLink
{
    /**
     * @var AbstractMapper the mapper that created this link
     */
    private $owner;

    /**
     * @var Container service container
     */
    private $container;

    /**
     * Fetch linked value according to the type of this link.
     * 
     * @param string|null $attribute the name of attribute that should be retreived.
     * Null (default) means all values should be retreived.
     * @return mixed fetched value
     */
    abstract public function getValue($attribute = null);

    /**
     * Populate linked value according to the type of this link.
     * 
     * @param mixed $value the value that should be populated
     * @param string|null $attribute the name of attribute that should be populated.
     * Null (default) means all values should be populated.
     */
    abstract public function setValue($value, $attribute = null);

    /**
     * Saves linked models.
     * @param boolean $calledBeforeOwnerSaving this param indicates whether the method has been called before or after owner saving.
     * @return boolean whether the model(s) has been successfully saved.
     */
    abstract protected function saveInternal($calledBeforeOwnerSaving);

    /**
     * Deletes linked models.
     * @param boolean $calledBeforeOwnerDeleting this param indicates whether the method has been called before or after owner deleting.
     * @return boolean whether the model(s) has been successfully deleted.
     */
    abstract protected function deleteInternal($calledBeforeOwnerDeleting);

    /**
     * @return AbstractMapper the mapper that created this link
     */
    public function getOwner()
    {
        if ($this->owner === null)
        {
            throw new \UnexpectedValueException('The "' . static::class . '" was incorrectly configured, owner property is requred.');
        }
        return $this->owner;
    }

    /**
     * @param AbstractMapper $owner the mapper that created this link
     * @return static $this
     */
    public function setOwner(AbstractMapper $owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return Container service container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param Container $container service container
     * @return static $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Saves linked models.
     * @param boolean $withTransaction
     * @param boolean|null $calledBeforeOwnerSaving this param indicates whether the method has been called before or after owner saving.
     * Default is null that meaning this method was called directly.
     * @return boolean whether the model(s) has been successfully saved.
     * @throws \Exception
     */
    public function save($withTransaction = true, $calledBeforeOwnerSaving = null)
    {
        $save = function () use ($calledBeforeOwnerSaving)
        {
            return $calledBeforeOwnerSaving === null
                ? $this->saveInternal(true) && $this->saveInternal(false)
                : $this->saveInternal($calledBeforeOwnerSaving);
        };

        if (!$withTransaction)
        {
            return $save();
        }
        return $this->getOwner()->withTransaction($save, 'Cannot save "' . static::class . '" links of "' . get_class($this->getOwner()->getModel()) . '" model.');
    }

    /**
     * Deletes linked models.
     * @param boolean $withTransaction
     * @param boolean|null $calledBeforeOwnerDeleting this param indicates whether the method has been called before or after owner deleting.
     * Default is null that meaning this method was called directly.
     * @return boolean whether the model(s) has been successfully deleted.
     * @throws \Exception
     */
    public function delete($withTransaction = true, $calledBeforeOwnerDeleting = null)
    {
        $delete = function () use ($calledBeforeOwnerDeleting)
        {
            return $calledBeforeOwnerDeleting === null
                ? $this->deleteInternal(true) && $this->deleteInternal(false)
                : $this->deleteInternal($calledBeforeOwnerDeleting);
        };

        if (!$withTransaction)
        {
            return $delete();
        }
        return $this->getOwner()->withTransaction($delete, 'Cannot delete "' . static::class . '" links of "' . get_class($this->getOwner()->getModel()) . '" model.');
    }

    /**
     * Resets relation of owner's model.
     * @param string $relationName
     */
    protected function resetOwnerRelation($relationName)
    {
        $ownerModel = $this->getOwner()->getModel();
        $ownerModel->setRelations(Arr::except($ownerModel->getRelations(), $relationName));
    }
}

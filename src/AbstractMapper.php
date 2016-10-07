<?php

namespace Bicycle\Mapper;

use ArrayAccess;
use JsonSerializable;
use Illuminate\Container\Container;
use Illuminate\Contracts;

/**
 * AbstractMapper is abstract class for your mappers.
 * You need only to override the two main methods:
 * - attributesMap(): map of your attributes
 * - modelClass(): the name of your model with which the mapper should to work
 * For more info see phpdocs of this methods.
 * 
 * Also you can see more useful features if you look to phpdocs of methods:
 * - linksConfgig(): you may configure links between this and other mappers
 * - accessors(): you may define your custom logic that will modify result before getting any attribute
 * - mutators(): you may define your custom logic that will modify values before setting any attribute
 */
abstract class AbstractMapper implements
    ArrayAccess,
    Contracts\Support\Arrayable,
    Contracts\Support\Jsonable,
    JsonSerializable
{
    const EVENT_SAVING = 'mapper.saving';
    const EVENT_SAVED = 'mapper.saved';
    const EVENT_INSERTING = 'mapper.inserting';
    const EVENT_INSERTED = 'mapper.inserted';
    const EVENT_UPDATING = 'mapper.updating';
    const EVENT_UPDATED = 'mapper.updated';
    const EVENT_DELETING = 'mapper.deleting';
    const EVENT_DELETED = 'mapper.deleted';

    /**
     * Keeps configurations for all links.
     * @var Links\AbstractLink[]|array[]
     */
    private $links;

    /**
     * Keeps the model that associated with this mapper.
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    private $model;

    /**
     * Keeps config of attributes map.
     * @var array
     */
    private $map;

    /**
     * Keeps the mapper's accessors.
     * @var callable[]
     */
    private $accessors;

    /**
     * Keeps the mapper's mutators.
     * @var callable[]
     */
    private $mutators;

    /**
     * This method should return map for your attributes.
     * 
     * Keys of this map are attributes that will be in this map. If key is kept the value will be used instead of.
     * You may directly getting or setting this attributes.
     * 
     * Values in this map are meaning how the mapper should retrieve or put value of current attribute.
     * You may use dot in values that means the attribute should be accesed via any link.
     * 
     * For example:
     * ```php
     *  return [
     *      'gender',
     *      'category' => 'category_id',
     *      'email' => 'emailContact.value',
     *      'addresses',
     *  ];
     * ```
     * 
     * When you'll create this map, you may use direct access to this attributes:
     * ```php
     *  $mapper = new MyMapper();
     * 
     *  echo $mapper->gender; // similar to: echo $mapper->getModel()->gender;
     *  $mapper->gender = 'male'; // similar to: $mapper->getModel()->gender = 'male';
     * 
     *  echo $mapper->category; // similar to: echo $mapper->getModel()->category_id;
     *  $mapper->category = 15; // similar to: $mapper->getModel()->category_id = 15;
     * 
     *  echo $mapper->email; // similar to: echo $mapper->getLink('emailContact')->value;
     *  $mapper->email = 'test@example.org'; // similar to: $mapper->getLink('emailContact')->email = 'test@example.org';
     * ```
     * 
     * Also you may pass here only link value.
     * For example, if you have `addresses` one2many link, and each of adress has 'name' & 'street' attributes.
     * Then the following code will be correct:
     * ```php
     *  foreach ($mapper->addresses as $addressId => $address) {
     *      echo "ID: $addressId, name: '$address->name', street: '$address->street'";
     *  }
     * 
     *  // when put to this property array than all old addresses will be populated with new data
     *  // other addresses will be added. If any address is not exist in this array the mapper remember that it should be deleted on save.
     *  $mapper->addresses = [
     *      15 => ['name' => 'Address 1'], // e.g. change only name of address with id == 15
     *      ['name' => 'Address 2', 'street' => 'Any Street'], // e.g. new address
     *  ];
     * ```
     *
     * @return array the map of attribtues for this mapper.
     */
    abstract protected function attributesMap();

    /**
     * The name of model with which this mapper is associated.
     * @return string the name of model class.
     */
    abstract protected function modelClass();

    /**
     * This method returns config for links for this mapper.
     * 
     * The keys of array are names of links.
     * You may access the link in future directly through this name.
     * 
     * The value of the array is array config for creating the link.
     * For more convenience you may use static methods of LinkFactory like one2one(), one2many and others.
     * For more information see phpdoc for LinkFactory::TYPE_* constants.
     * 
     * Example:
     * ```php
     *  // defining of links configs
     *  return [
     *      'addresses' => LinkFactory::one2many(AddressMapper::class, 'addresses'),
     *      'emailContact' => [
     *          'type' => LinkFactory::TYPE_ONE2ONE,
     *          'mapperClass' => EmailMapper::class,
     *          'retreiveRelation' => 'emailContact',
     *          'attachRelation' => 'contacts',
     *      ],
     *      'user' => LinkFactory::belongs2One(UserMapper::class, 'user'),
     *      'categoryIds' => LinkFactory::many2many('categories')
     *  ];
     *  // ...
     * 
     *  // using links
     *  $mapper = new MyMapper();
     *  $mapper->user->name = 'Some Name';
     *  
     *  foreach ($mapper->addresses as $address)
     *  {
     *      // manipulate with each $address
     *  }
     *  
     *  implode(', ', $mapper->categoryIds); // returns comma separated IDs of categories
     *  $mapper->categoryIds = [2, 5, 6]; // change the set of category IDs
     * ```
     * 
     * @return array[] config of links available in this mapper.
     */
    protected function linksConfig()
    {
        return [];
    }

    /**
     * This method return the array of accessors for this mapper.
     * 
     * Each accessor will be called when any attribute retreiving from mapper.
     * You may add you custom logic and modify value that should be returned by mapper.
     * 
     * Example:
     * ```php
     *  return [
     *      'name' => function ($name) {
     *          return Str::ucfirst($name);
     *      },
     *  ];
     * ```
     * 
     * @return callable[] array of accessors defined in this mapper through getter.
     * Keys of this array are the names of attributes.
     * Values are any callable like Closure or array in [$object, 'method'] format.
     * 
     * The following arguments will be passed in callable when it will be called:
     * - $result: mixed, the result that was fetched by the mapper before calling of the accessor
     * - $attribute: string, the name of attribute
     * - $this: AbstractMapper, this mapper
     * - $mappedAttribute: string, the name of associated in `attributeMap()` mapped attribute name
     * 
     * The callable must return the value that will be returned by this mapper.
     */
    protected function accessors()
    {
        return [];
    }

    /**
     * This method return the array of mutators for this mapper through setter.
     * 
     * Each mutator will be called when any attribute populated in mapper.
     * You may add you custom logic and modify value that should be stored in mapper.
     * 
     * Example:
     * ```php
     *  return [
     *      'password' => function ($password) {
     *          return bcrypt($password);
     *      },
     *  ];
     * ```
     * 
     * @return callable[] array of mutators defined in this mapper.
     * Keys of this array are the names of attributes.
     * Values are any callable like Closure or array in [$object, 'method'] format.
     * 
     * The following arguments will be passed in callable when it will be called:
     * - $value: mixed, the value that was populated through setter before calling of the mutator
     * - $attribute: string, the name of attribute
     * - $this: AbstractMapper, this mapper
     * - $mappedAttribute: string, the name of associated in `attributeMap()` mapped attribute name
     * 
     * The callable must return the value that will be populated in this mapper.
     */
    protected function mutators()
    {
        return [];
    }

    /**
     * The mapper constructor. If you override it do not forgot call this parent constructor.
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * The model object with which this mapper will be associated.
     * If null then the mapper creates new model when it will be needed.
     */
    public function __construct($model = null)
    {
        $this->model = $model ?: null;
    }

    /**
     * Return the model that associated with this mapper.
     * @return \Illuminate\Database\Eloquent\Model the model object.
     */
    public function getModel()
    {
        if ($this->model === null)
        {
            $this->model = $this->createModel();
        }
        return $this->model;
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exceptions\UnknownPropertyException
     */
    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws Exceptions\UnknownPropertyException
     */
    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        $this->offsetUnset($name);
    }

    /**
     * This is magical PHP method that will be called after clonning of the mapper.
     */
    public function __clone()
    {
        $this->model = clone $this->model;
        $this->refreshPreparedData();
    }

    /**
     * @param string $offset
     * @return mixed
     * @throws Exceptions\UnknownPropertyException
     */
    public function offsetGet($offset)
    {
        if ($this->hasMappedAttribute($offset))
        {
            return $this->fetchMappedValue($offset);
        }
        elseif ($this->hasLink($offset))
        {
            return $this->getLink($offset)->getValue();
        }

        $message = 'The "' . static::class . "\" mapper has not \"$offset\" property.";
        try {
            return $this->getModelAttribute($offset);
        } catch (Exceptions\UnknownPropertyException $ex) {
            throw new Exceptions\UnknownPropertyException($message, $ex->getCode(), $ex);
        }
        throw new Exceptions\UnknownPropertyException($message);
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @throws Exceptions\UnknownPropertyException
     */
    public function offsetSet($offset, $value)
    {
        if ($this->hasMappedAttribute($offset))
        {
            $this->populateMappedValue($offset, $value);
            return;
        }
        throw new Exceptions\UnknownPropertyException('The "' . static::class . "\" mapper has not writable \"$offset\" property.");
    }

    /**
     * @param string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        try
        {
            return $this->offsetGet($offset) !== null;
        }
        catch (Exceptions\UnknownPropertyException $ex)
        {
            return false;
        }
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        try
        {
            $this->offsetSet($offset, null);
        }
        catch (Exceptions\UnknownPropertyException $ex)
        {
        }
    }

    /**
     * Fetches the mapped value.
     * This method analyzes your map defined in `attributesMap()` and returns appropriate value.
     * Also this method will use accessor if it defined for this $attribute in `accessors()`.
     * 
     * @param string $attribute the name of attribute
     * @return mixed fetched value
     * @throws Exceptions\UnknownPropertyException
     */
    public function fetchMappedValue($attribute)
    {
        $mappedAttribute = $this->getMappedAttribute($attribute);

        if (false !== $pos = strpos($mappedAttribute, '.'))
        {
            $result = $this->getLink(substr($mappedAttribute, 0, $pos))->getValue(substr($mappedAttribute, $pos + 1));
        }
        elseif ($this->hasLink($mappedAttribute))
        {
            $result = $this->getLink($mappedAttribute)->getValue();
        }
        else
        {
            $result = $this->getModel()->{$mappedAttribute};
        }

        $this->prepareAccessors();
        if (isset($this->accessors[$attribute]))
        {
            $result = call_user_func($this->accessors[$attribute], $result, $attribute, $this, $mappedAttribute);
        }
        return $result;
    }

    /**
     * Populates the value in mapped attribute.
     * This method analyzes your map defined in `attributesMap()` and sets $value in the appropriate attribute.
     * Also this method will use mutator to modify value before if it defined for this $attribute in `mutators()`.
     * 
     * @param string $attribute the name of attribute
     * @param mixed $value the value that should be populated
     * @throws Exceptions\UnknownPropertyException
     */
    public function populateMappedValue($attribute, $value)
    {
        $mappedAttribute = $this->getMappedAttribute($attribute);

        $this->prepareMutators();
        if (isset($this->mutators[$attribute]))
        {
            $value = call_user_func($this->mutators[$attribute], $value, $attribute, $this, $mappedAttribute);
        }

        if (false !== $pos = strpos($mappedAttribute, '.'))
        {
            $this->getLink(substr($mappedAttribute, 0, $pos))->setValue($value, substr($mappedAttribute, $pos + 1));
        }
        elseif ($this->hasLink($mappedAttribute))
        {
            $this->getLink($mappedAttribute)->setValue($value);
        }
        else
        {
            $this->getModel()->{$mappedAttribute} = $value;
        }
    }

    /**
     * Checks and returns whether the mapper has map for $attribute.
     * @param string $attribute the name of attribute
     * @return boolean whether the mapper has map for $attribute
     */
    public function hasMappedAttribute($attribute)
    {
        $this->prepareMap();
        return isset($this->map[$attribute]);
    }

    /**
     * Returns the map for the $attribute.
     * 
     * @param string $attribute the name of attribute
     * @return string the mapped attribute name
     * @throws Exceptions\UnknownPropertyException
     */
    public function getMappedAttribute($attribute)
    {
        $this->prepareMap();
        if (!isset($this->map[$attribute]))
        {
            throw new Exceptions\UnknownPropertyException("Unknown attribute '$attribute'.");
        }
        return $this->map[$attribute];
    }

    /**
     * Returns array of all attribute names.
     * @return array the names of all attributes
     */
    public function attributes()
    {
        $this->prepareMap();
        return array_keys($this->map);
    }

    /**
     * Returns values of all attributes listed in `attributesMap()`
     * @return array in attribute => value format.
     */
    public function getMappedAttributes()
    {
        $result = [];
        foreach ($this->attributes() as $attribute)
        {
            $result[$attribute] = $this->fetchMappedValue($attribute);
        }
        return $result;
    }

    /**
     * Set several values of attributes through array.
     * This method uses `populateMappedValue()` internally.
     * 
     * @param array $attributes the array of value that shoul be populated in attribute => value format.
     * @param boolean $safeOnly whether the method should set only attributes listed in `attributesMap()`.
     * Otherwise if map for attribute is not found the value will be directly populated in model. Default is true.
     */
    public function setMappedAttributes($attributes, $safeOnly = true)
    {
        foreach (collect($attributes) as $key => $value)
        {
            if ($this->hasMappedAttribute($key))
            {
                $this->populateMappedValue($key, $value);
            }
            elseif (!$safeOnly)
            {
                $this->getModel()->{$key} = $value;
            }
        }
    }

    /**
     * Retrieves value of attribute from model associated with this mapper.
     * This method also checks whether the model has attribute or relation with the same name.
     * 
     * @param string $attribute the name of attribute
     * @return mixed fetched value
     * @throws Exceptions\UnknownPropertyException if attribute is not found in model
     */
    protected function getModelAttribute($attribute)
    {
        $model = $this->getModel();
        if (array_key_exists($attribute, $model->getAttributes()) || $model->hasGetMutator($attribute))
        {
            return $model->getAttributeValue($attribute);
        }
        elseif ($model->relationLoaded($attribute) || method_exists($model, $attribute))
        {
            return $model->getRelationValue($attribute);
        }
        throw new Exceptions\UnknownPropertyException('The model "' . get_class($model) . "\" has neither attribute nor relation '$attribute'.");
    }

    /**
     * Checks and returns whether the mapper has link with appropriate $name.
     * 
     * @param string $name the name of link that should be checked.
     * @return boolean whether the link exists or not
     */
    public function hasLink($name)
    {
        $this->prepareLinks();
        return isset($this->links[$name]);
    }

    /**
     * Returns the link object by link name.
     * 
     * @param string $name the name of link
     * @return Links\AbstractLink the link object
     * @throws Exceptions\UnknownPropertyException if link was not found
     */
    public function getLink($name)
    {
        $this->prepareLinks();
        if (!isset($this->links[$name]))
        {
            throw new Exceptions\UnknownPropertyException("Unexpected link name: '$name'.");
        }
        if (!$this->links[$name] instanceof Links\AbstractLink)
        {
            $this->links[$name] = $this->createLink($this->links[$name]);
        }
        return $this->links[$name];
    }

    /**
     * Returns the list of all links defined in `linksCOnfig()` of this mapper.
     * 
     * @return Links\AbstractLink[] all mapper's links
     */
    public function getLinks()
    {
        $this->prepareLinks();
        $result = [];
        foreach (array_keys($this->links) as $name)
        {
            $result[$name] = $this->getLink($name);
        }
        return $result;
    }

    /**
     * @return Contracts\Container\Container
     */
    public function getContainer()
    {
        return Container::getInstance();
    }

    /**
     * Creates link object by configuration array.
     * 
     * @param array $config config for link
     * @return Links\AbstractLink the created link object
     */
    protected function createLink(array $config)
    {
        $factory = $this->getContainer()->make(Links\LinkFactory::class, [
            'owner' => $this,
        ]);
        /* @var $factory Links\LinkFactory */
        return $factory->create($config);
    }

    /**
     * Creates model by class name defined in `modelClass()` method.
     * 
     * @return \Illuminate\Database\Eloquent\Model the created object.
     */
    protected function createModel()
    {
        return $this->getContainer()->make($this->modelClass());
    }

    /**
     * Internal method that saves the model attached to this mapper and all linked submodels.
     * Do not use this method directly, use `save()` instead of.
     * 
     * @return boolean whether the mapper has been successfully saved.
     */
    protected function saveInternal()
    {
        $isInsert = !$this->getModel()->exists;
        if ($this->beforeSave($isInsert) === false)
        {
            return false;
        }

        $this->prepareLinks();
        $save = function ($isBefore)
        {
            foreach ($this->links as $link)
            {
                // Saving only initialized links, because non-inited link is meaning that no data changed.
                if ($link instanceof Links\AbstractLink && !$link->save(false, $isBefore))
                {
                    return false;
                }
            }
            return true;
        };

        $result = $save(true) && $this->getModel()->save() && $save(false);
        if ($result)
        {
            $this->afterSave($isInsert);
        }
        return $result;
    }

    /**
     * Saves the model attached to this mapper and all linked submodels.
     * 
     * @param boolean $withTransaction whether the saving process must be done with transaction or not.
     * @return boolean wheteher the mapper and all it's links has been successfully saved.
     * @throws \Exception
     */
    public function save($withTransaction = true)
    {
        return $withTransaction
            ? $this->withTransaction([$this, 'saveInternal'], 'Cannot save "' . static::class . '" mapper\'s model and/or related links.')
            : $this->saveInternal();
    }

    /**
     * This method will be called before mapper saving.
     * You may override it for implementing your custom logic.
     * @return bool you may return false if you want to stop saving process.
     * @param boolean $isInsert whether it is inserting process or otherwise - updating.
     */
    protected function beforeSave($isInsert)
    {
        return $this->fireMapperEvent(static::EVENT_SAVING) !== false &&
            $this->fireMapperEvent($isInsert ? static::EVENT_INSERTING : static::EVENT_UPDATING) !== false;
    }

    /**
     * This method will be called after mapper saving.
     * You may override it for implementing your custom logic.
     * @param boolean $isInsert whether it is inserting process or otherwise - updating.
     */
    protected function afterSave($isInsert)
    {
        $this->fireMapperEvent($isInsert ? static::EVENT_INSERTED : static::EVENT_UPDATED, false);
        $this->fireMapperEvent(static::EVENT_SAVED, false);
    }

    /**
     * Internal method that deletes the model attached to this mapper and all linked submodels.
     * Do not use this method directly, use `save()` instead of.
     * 
     * @return boolean whether the mapper has been successfully deleted.
     */
    protected function deleteInternal()
    {
        if ($this->beforeDelete() === false)
        {
            return false;
        }

        $links = $this->getLinks();
        $delete = function ($isBefore) use ($links)
        {
            foreach ($links as $link)
            {
                if (!$link->delete(false, $isBefore))
                {
                    return false;
                }
            }
            return true;
        };
        $result = $delete(true) && ($this->getModel()->delete() !== false) && $delete(false);

        if ($result)
        {
            $this->afterDelete();
        }
        return $result;
    }

    /**
     * Deletes the model attached to this mapper and all linked submodels.
     * 
     * @param boolean $withTransaction whether the deleting process must be done with transaction or not.
     * @return boolean whether the mapper has been successfully deleted
     * @throws \Exception
     */
    public function delete($withTransaction = true)
    {
        return $withTransaction
            ? $this->withTransaction([$this, 'deleteInternal'], 'Cannot delete "' . static::class . '" mapper\'s model and/or related links.')
            : $this->deleteInternal();
    }

    /**
     * This method will be called before mapper deleting.
     * You may override it for implementing your custom logic.
     * @return bool you may return false if you want to stop deleting process.
     */
    protected function beforeDelete()
    {
        return $this->fireMapperEvent(static::EVENT_DELETING) !== false;
    }

    /**
     * This method will be called after mapper deleting.
     * You may override it for implementing your custom logic.
     */
    protected function afterDelete()
    {
        $this->fireMapperEvent(static::EVENT_DELETED, false);
    }

    /**
     * Calls $callback with transaction wrapping.
     * If $callback returns false then the method will rollback database changes and throw exception.
     * 
     * @param callable $callback the function that should be called
     * @param string $error message for exception if it will be thrown
     * @param array $params additional params that will be passed in $callback
     * @return boolean whether the callbackk has been succesfully executed.
     * @throws \Exception
     */
    public function withTransaction($callback, $error = null, array $params = [])
    {
        $connection = $this->getModel()->getConnection();
        $connection->beginTransaction();

        try
        {
            if (!call_user_func_array($callback, $params))
            {
                throw new \Exception($error ?: 'Processing with database has been failed in "' . static::class . '" mapper.');
            }
            $connection->commit();
        }
        catch (\Exception $ex)
        {
            $connection->rollBack();
            throw $ex;
        }
        catch (\Throwable $ex)
        {
            $connection->rollBack();
            throw $ex;
        }

        return true;
    }

    /**
     * Prepares attributes map
     */
    protected function prepareMap()
    {
        if ($this->map === null)
        {
            $map = [];
            foreach ($this->attributesMap() as $key => $value)
            {
                $map[is_int($key) ? $value : $key] = $value;
            }
            $this->map = $map;
        }
    }

    /**
     * Prepares links property.
     */
    protected function prepareLinks()
    {
        if ($this->links === null)
        {
            $this->links = $this->linksConfig();
        }
    }

    /**
     * Prepares accessors.
     */
    protected function prepareAccessors()
    {
        if ($this->accessors === null)
        {
            $this->accessors = $this->accessors();
        }
    }

    /**
     * Prepares mutators.
     */
    protected function prepareMutators()
    {
        if ($this->mutators === null)
        {
            $this->mutators = $this->mutators();
        }
    }

    /**
     * Published getter for attributes map.
     * @return array map of attributes.
     */
    public function getMap()
    {
        $this->prepareMap();
        return $this->map;
    }

    /**
     * Published getter for accessors.
     * @return callable[] array of defined accesors.
     */
    public function getAccessors()
    {
        $this->prepareAccessors();
        return $this->accessors;
    }

    /**
     * Published getter for mutators.
     * @return callable[] array of defined mutators.
     */
    public function getMutators()
    {
        $this->prepareMutators();
        return $this->mutators;
    }

    /**
     * Resets all prepared data.
     */
    protected function refreshPreparedData()
    {
        $this->map = null;
        $this->links = null;
        $this->accessors = null;
        $this->mutators = null;
    }

    /**
     * Fires the event of this mapper.
     * 
     * @param string $eventName the name of event
     * @param boolean $halt
     */
    protected function fireMapperEvent($eventName, $halt = true)
    {
        return \Event::fire("$eventName: " . static::class, $this, $halt);
    }

    /**
     * @inheritdoc
     */
    public function toJson($options = 0)
    {
        return collect($this->getMappedAttributes())->toJson($options);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return collect($this->getMappedAttributes())->jsonSerialize();
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return collect($this->getMappedAttributes())->toArray();
    }
}

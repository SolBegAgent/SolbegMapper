<?php

namespace Bicycle\Mapper\Links;

use Bicycle\Mapper\AbstractMapper;
use Bicycle\Mapper\SimpleMapper;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

/**
 * LinkFactory is used for creating link objects.
 *
 * @author alexey.sejnov <alexey.sejnov@solbeg.com>
 */
class LinkFactory
{
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
     * @see One2One link class and constructor for more info about this link type.
     */
    const TYPE_ONE2ONE = 'one2one';

    /**
     * One2Many link used when model of your mapper has many another submodels which data you want to modify jointly.
     * 
     * For example, you are writing mapper for user model. And you have `addresses` table with `user_id` column.
     * You may add/edit/delete user's addresses directly through user mapper.
     * 
     * This link type supports two variations:
     * 1. The `addresses` has `user_id` column. And you have appropriate `hasMany` eloquent relation in user model.
     * 2. You have the third table (e.g. `user_address`) that links this two tables. And you have appropriate `belongsToMany` eloquent relation.
     * 
     * @see One2Many link class and constructor for more info about this link type.
     */
    const TYPE_ONE2MANY = 'one2many';

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
     * @see Belongs2One link class and constructor for more info about this link type.
     */
    const TYPE_BELONGS2ONE = 'belongs2one';

    /**
     * Many2Many link used when model of your mapper associated as many-to-many with another entities.
     * 
     * For example, you have `users`, `team` and `users_team` tables.
     * And you have to edit team IDs that associated with user when you edit this user.
     * In this case you may use this link type. So your link will return and will may to populate array of IDs.
     * 
     * @see Many2Many link class and constructor for more info about this link type.
     */
    const TYPE_MANY2MANY = 'many2many';

    /**
     * @var array map from type name to appropriate class name.
     */
    public static $aliases = [
        self::TYPE_ONE2ONE => One2One::class,
        self::TYPE_ONE2MANY => One2Many::class,
        self::TYPE_BELONGS2ONE => Belongs2One::class,
        self::TYPE_MANY2MANY => Many2Many::class,
    ];

    /**
     * @var AbstractMapper the mapper that was create this link.
     */
    protected $owner;

    /**
     * @var Container service container
     */
    protected $container;

    /**
     * Shortcut for creating one2one links.
     * @see TYPE_ONE2ONE constant for more info.
     * 
     * @param string $mapperClass the name of mapper class with which this mapper should be associated.
     * @param string $relation the name of relation that will be used for retrieving and attaching linked models.
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function one2one($mapperClass, $relation, array $params = [])
    {
        $relationParams = [
            isset($params['retreiveRelation']) ? 'attachRelation' : 'retreiveRelation' => $relation,
        ];
        return array_merge([
            'type' => self::TYPE_ONE2ONE,
            'mapperClass' => $mapperClass,
        ], $relationParams, $params);
    }

    /**
     * Shortcut for creating one2one links with simple mapper.
     * @see TYPE_ONE2ONE constant for more info about link.
     * @see SimpleMapper for more info about it.
     * 
     * @param string $relation the name of relation that will be used for retrieving and attaching linked models.
     * @param string $modelClass the name of model class with which model of your mapper should be associated.
     * @param array $attributes attributes map for your linked model
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function one2oneSimple($relation, $modelClass, array $attributes, array $params = [])
    {
        Arr::set($params, 'mapperParams.modelClass', $modelClass);
        Arr::set($params, 'mapperParams.attributes', $attributes);
        return static::one2one(SimpleMapper::class, $relation, $params);
    }

    /**
     * Shortcut for creating one2many links.
     * @see TYPE_ONE2MANY constant for more info.
     * 
     * @param string $mapperClass the name of mapper class with which this mapper should be linked.
     * @param string $relation the name of relation that will be used for retrieving and attaching linked models.
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function one2many($mapperClass, $relation, array $params = [])
    {
        return array_merge([
            'type' => self::TYPE_ONE2MANY,
            'mapperClass' => $mapperClass,
            'relation' => $relation,
        ], $params);
    }

    /**
     * Shortcut for creating one2many links with simple mapper.
     * @see TYPE_ONE2MANY constant for more info about link.
     * @see SimpleMapper for more info about it.
     * 
     * @param strig $relation the name of relation that will be used for retrieving and attaching linked models.
     * @param string $modelClass the name of model class with which model of your mapper should be linked.
     * @param array $attributes attributes map for your linked model
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function one2manySimple($relation, $modelClass, array $attributes, array $params = [])
    {
        Arr::set($params, 'mapperParams.modelClass', $modelClass);
        Arr::set($params, 'mapperParams.attributes', $attributes);
        return static::one2many(SimpleMapper::class, $relation, $params);
    }

    /**
     * Shortcut for creating belongs2one links.
     * @see TYPE_BELONGS2ONE constant for more info.
     * 
     * @param string $mapperClass the name of mapper class with which this mapper should be linked.
     * @param string $relation the name of relation that will be used for retrieving and attaching linked models.
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function belongs2One($mapperClass, $relation, array $params = [])
    {
        return array_merge([
            'type' => self::TYPE_BELONGS2ONE,
            'mapperClass' => $mapperClass,
            'relation' => $relation,
        ], $params);
    }

    /**
     * Shortcut for creating belongs2one links with simple mapper.
     * @see TYPE_BELONGS2ONE constant for more info about link.
     * @see SimpleMapper for more info about it.
     * 
     * @param string $relation the name of relation that will be used for retrieving and attaching linked models.
     * @param string $modelClass the name of model class with which model of your mapper should be linked.
     * @param array $attributes attributes map for your linked model
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function belongs2OneSimple($relation, $modelClass, array $attributes, array $params = [])
    {
        Arr::set($params, 'mapperParams.modelClass', $modelClass);
        Arr::set($params, 'mapperParams.attributes', $attributes);
        return static::belongs2One(SimpleMapper::class, $relation, $params);
    }

    /**
     * Shortcut for creating many2many links.
     * @see TYPE_MANY2MANY constant for more info.
     * 
     * @param string $relation the name of relation that will be used for retrieving and syncing linked ids.
     * @param array $params additional params of the link
     * @return array config that may be used for creating this link
     */
    public static function many2many($relation, array $params = [])
    {
        return array_merge([
            'type' => self::TYPE_MANY2MANY,
            'relation' => $relation,
        ], $params);
    }

    /**
     * @param AbstractMapper $owner
     * @param Container $container
     */
    public function __construct(AbstractMapper $owner, Container $container)
    {
        $this->owner = $owner;
        $this->container = $container;
    }

    /**
     * Creates link from configuration array.
     * 
     * @param array $config link's configuration
     * @return AbstractLink the created link object
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function create(array $config)
    {
        if (!isset($config['type']))
        {
            throw new \InvalidArgumentException('Configuration of mapper link must have any "type".');
        }

        $type = $config['type'];
        unset($config['type']);

        if (isset(static::$aliases[$type]))
        {
            $type = static::$aliases[$type];
        }

        $result = $this->container->make($type, array_merge([
            'owner' => $this->owner,
        ], $config));
        if (!$result instanceof AbstractLink)
        {
            throw new \UnexpectedValueException('Invalid mapper link configuration, it must keep config for an instace of "' . AbstractLink::class . '".');
        }

        $result->setOwner($this->owner);
        $result->setContainer($this->container);
        return $result;
    }
}

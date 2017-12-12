<?php

namespace ByJG\Authenticate\Definition;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Mapper;

/**
 * Structure to represent the users
 */
class UserDefinition
{

    protected $table = 'users';
    protected $userid = 'userid';
    protected $name = 'name';
    protected $email = 'email';
    protected $username = 'username';
    protected $password = 'password';
    protected $created = 'created';
    protected $admin = 'admin';

    const UPDATE="update";
    const SELECT="select";

    protected $closures = [ "select" => [], "update" => [] ];

    protected $loginField;

    protected $model;

    const LOGIN_IS_EMAIL="email";
    const LOGIN_IS_USERNAME="username";

    /**
     * Define the name of fields and table to store and retrieve info from database
     *
     * @param string $table
     * @param string $loginField
     * @param array $fieldDef
     */
    public function __construct(
        $table = 'users',
        $loginField = self::LOGIN_IS_USERNAME,
        $fieldDef = []
    ) {
        $this->table = $table;

        foreach ($fieldDef as $property => $value) {
            $this->checkProperty($property);
            $this->{$property} = $value;
        }

        $this->model = UserModel::class;

        $this->defineClosureForUpdate('password', function ($value) {
            // Already have a SHA1 password
            if (strlen($value) === 40) {
                return $value;
            }

            // Leave null
            if (empty($value)) {
                return null;
            }

            // Return the hash password
            return strtoupper(sha1($value));
        });

        if ($loginField !== self::LOGIN_IS_USERNAME && $loginField !== self::LOGIN_IS_EMAIL) {
            throw new \InvalidArgumentException('Login field is invalid. ');
        }
        $this->loginField = $loginField;
    }

    /**
     * @return string
     */
    public function table()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getUserid()
    {
        return $this->userid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @return string
     */
    public function getAdmin()
    {
        return $this->admin;
    }

    /**
     * @return string
     */
    public function loginField()
    {
        return $this->{"get" . $this->loginField}();
    }

    private function checkProperty($property)
    {
        if (!isset($this->{$property})) {
            throw new \InvalidArgumentException('Invalid property');
        }
    }

    /**
     * @param $event
     * @param $property
     * @param \Closure $closure
     */
    private function updateClosureDef($event, $property, $closure)
    {
        $this->checkProperty($property);
        $this->closures[$event][$property] = $closure;
    }

    private function getClosureDef($event, $property)
    {
        $this->checkProperty($property);

        if (!$this->existsClosure($event, $property)) {
            return Mapper::defaultClosure();
        }

        return $this->closures[$event][$property];
    }

    public function existsClosure($event, $property)
    {
        // Event not set
        if (!isset($this->closures[$event])) {
            return false;
        }

        // Event is set but there is no property
        if (!array_key_exists($property, $this->closures[$event])) {
            return false;
        }

        return true;
    }

    public function markPropertyAsReadOnly($property)
    {
        $this->updateClosureDef(self::UPDATE, $property, Mapper::doNotUpdateClosure());
    }

    public function defineClosureForUpdate($property, \Closure $closure)
    {
        $this->updateClosureDef(self::UPDATE, $property, $closure);
    }

    public function defineClosureForSelect($property, \Closure $closure)
    {
        $this->updateClosureDef(self::SELECT, $property, $closure);
    }

    public function getClosureForUpdate($property)
    {
        return $this->getClosureDef(self::UPDATE, $property);
    }

    /**
     * @param $property
     * @return \Closure
     */
    public function getClosureForSelect($property)
    {
        return $this->getClosureDef(self::SELECT, $property);
    }

    public function model()
    {
        return $this->model;
    }

    public function modelInstance()
    {
        $model = $this->model;
        return new $model();
    }
}

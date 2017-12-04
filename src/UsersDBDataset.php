<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\IteratorFilterSqlFormatter;
use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\Factory;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\MicroOrm\Updatable;

class UsersDBDataset extends UsersBase
{

    /**
     * @var \ByJG\MicroOrm\Repository
     */
    protected $userRepository;

    /**
     * @var \ByJG\MicroOrm\Repository
     */
    protected $propertiesRepository;

    /**
     * @var \ByJG\AnyDataset\DbDriverInterface
     */
    protected $provider;

    /**
     * UsersDBDataset constructor
     *
     * @param string $connectionString
     * @param UserDefinition $userTable
     * @param UserPropertiesDefinition $propertiesTable
     * @throws \ByJG\AnyDataset\Exception\NotFoundException
     * @throws \ByJG\AnyDataset\Exception\NotImplementedException
     */
    public function __construct(
        $connectionString,
        UserDefinition $userTable = null,
        UserPropertiesDefinition $propertiesTable = null
    ) {
        if (empty($userTable)) {
            $userTable = new UserDefinition();
        }

        if (empty($propertiesTable)) {
            $propertiesTable = new UserPropertiesDefinition();
        }

        $provider = Factory::getDbRelationalInstance($connectionString);
        $userMapper = new Mapper(
            UserModel::class,
            $userTable->getTable(),
            $userTable->getUserid()
        );
        $userMapper->addFieldMap(
            'userid',
            $userTable->getUserid(),
            $userTable->getClosureForUpdate('userid'),
            $userTable->getClosureForSelect('userid')
        );
        $userMapper->addFieldMap(
            'name',
            $userTable->getName(),
            $userTable->getClosureForUpdate('name'),
            $userTable->getClosureForSelect('name')
        );
        $userMapper->addFieldMap(
            'email',
            $userTable->getEmail(),
            $userTable->getClosureForUpdate('email'),
            $userTable->getClosureForSelect('email')
        );
        $userMapper->addFieldMap(
            'username',
            $userTable->getUsername(),
            $userTable->getClosureForUpdate('username'),
            $userTable->getClosureForSelect('username')
        );
        $userMapper->addFieldMap(
            'password',
            $userTable->getPassword(),
            $userTable->getClosureForUpdate('password'),
            $userTable->getClosureForSelect('password')
        );
        $userMapper->addFieldMap(
            'created',
            $userTable->getCreated(),
            $userTable->getClosureForUpdate('created'),
            $userTable->getClosureForSelect('created')
        );
        $userMapper->addFieldMap(
            'admin',
            $userTable->getAdmin(),
            $userTable->getClosureForUpdate('admin'),
            $userTable->getClosureForSelect('admin')
        );
        $this->userRepository = new Repository($provider, $userMapper);

        $propertiesMapper = new Mapper(
            UserPropertiesModel::class,
            $propertiesTable->getTable(),
            $propertiesTable->getId()
        );
        $propertiesMapper->addFieldMap('id', $propertiesTable->getId());
        $propertiesMapper->addFieldMap('name', $propertiesTable->getName());
        $propertiesMapper->addFieldMap('value', $propertiesTable->getValue());
        $propertiesMapper->addFieldMap('userid', $propertiesTable->getUserid());
        $this->propertiesRepository = new Repository($provider, $propertiesMapper);

        $this->userTable = $userTable;
        $this->propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $user
     */
    public function save(UserModel $user)
    {
        $this->userRepository->save($user);

        foreach ($user->getProperties() as $property) {
            $property->setUserid($user->getUserid());
            $this->propertiesRepository->save($property);
        }
    }

    /**
     * Add new user in database
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return bool
     * @throws UserExistsException
     */
    public function addUser($name, $userName, $email, $password)
    {
        if ($this->getByEmail($email) !== null) {
            throw new UserExistsException('Email already exists');
        }
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getUsername(), Relation::EQUAL, $userName);
        if ($this->getUser($filter) !== null) {
            throw new UserExistsException('Username already exists');
        }

        $model = new UserModel($name, $email, $userName, $password);
        $this->userRepository->save($model);

        return true;
    }

    /**
     * Get the users database information based on a filter.
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel[]
     */
    public function getIterator(IteratorFilter $filter = null)
    {
        if (is_null($filter)) {
            $filter = new IteratorFilter();
        }

        $param = [];
        $formatter = new IteratorFilterSqlFormatter();
        $sql = $formatter->getFilter($filter->getRawFilters(), $param);

        $query = Query::getInstance()
            ->table($this->getUserDefinition()->getTable())
            ->where($sql, $param);

        return $this->userRepository->getByQuery($query);
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel
     * */
    public function getUser($filter)
    {
        $result = $this->getIterator($filter);
        if (count($result) === 0) {
            return null;
        }

        $model = $result[0];

        $this->setPropertiesInUser($model);

        return $model;
    }

    /**
     * Remove the user based on his user login.
     *
     * @param string $login
     * @return bool
     * */
    public function removeByLoginField($login)
    {
        $user = $this->getByLoginField($login);

        if ($user !== null) {
            return $this->removeUserById($user->getUserid());
        }

        return false;
    }

    /**
     * Remove the user based on his user id.
     *
     * @param mixed $userId
     * @return bool
     * */
    public function removeUserById($userId)
    {
        $updtableProperties = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->getTable())
            ->where(
                "{$this->getUserPropertiesDefinition()->getUserid()} = :id",
                [
                    "id" => $this->getUserDefinition()->getUserid()
                ]
            );
        $this->propertiesRepository->deleteByQuery($updtableProperties);

        $this->userRepository->delete($userId);

        return true;
    }

    /**
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return bool
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     */
    public function addProperty($userId, $propertyName, $value)
    {
        //anydataset.Row
        $user = $this->getById($userId);
        if (empty($user)) {
            return false;
        }

        if (!$this->hasProperty($userId, $propertyName, $value)) {
            $propertiesModel = new UserPropertiesModel($propertyName, $value);
            $propertiesModel->setUserid($userId);
            $this->propertiesRepository->save($propertiesModel);
        }

        return true;
    }

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param int $userId User Id
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * */
    public function removeProperty($userId, $propertyName, $value = null)
    {
        $user = $this->getById($userId);
        if ($user !== null) {

            $updateable = Updatable::getInstance()
                ->table($this->getUserPropertiesDefinition()->getTable())
                ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ["id" => $userId])
                ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

            if (!empty($value)) {
                $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
            }

            $this->propertiesRepository->deleteByQuery($updateable);

            return true;
        }

        return false;
    }

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * */
    public function removeAllProperties($propertyName, $value = null)
    {
        $updateable = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->getTable())
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

        if (!empty($value)) {
            $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
        }

        $this->propertiesRepository->deleteByQuery($updateable);

        return true;
    }

    /**
     * Return all property's fields from this user
     *
     * @param UserModel $userRow
     */
    protected function setPropertiesInUser(UserModel $userRow)
    {
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->getTable())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' =>$userRow->getUserid()]);
        $userRow->setProperties($this->propertiesRepository->getByQuery($query));
    }
}

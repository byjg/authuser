<?php

namespace ByJG\Authenticate;

use PHPUnit_Framework_TestCase;

/**
 * Created by PhpStorm.
 * User: jg
 * Date: 24/04/16
 * Time: 20:21
 */
class UsersAnyDatasetTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var UsersAnyDataset
     */
    protected $object;

    protected $prefix = "";
    
    public function setUp()
    {
        $this->prefix = "user";

        $this->object = new UsersAnyDataset('php://memory');
        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $user = $this->object->getByUsername('john');
        $this->assertEquals('john', $user->get($this->object->getUserTable()->id));
        $this->assertEquals('John Doe', $user->get($this->object->getUserTable()->name));
        $this->assertEquals('john', $user->get($this->object->getUserTable()->username));
        $this->assertEquals('johndoe@gmail.com', $user->get($this->object->getUserTable()->email));
        $this->assertEquals('91DFD9DDB4198AFFC5C194CD8CE6D338FDE470E2', $user->get($this->object->getUserTable()->password));
    }

    /**
     * @expectedException \ByJG\Authenticate\Exception\UserExistsException
     */
    public function testAddUserError()
    {
        $this->object->addUser('some user with same username', 'user2', 'bla@gmail.com', 'mypassword');
    }

    public function testAddUser_generatedId()
    {
        $mock = $this->getMockBuilder('\ByJG\Authenticate\UsersAnyDataset')
            ->setMethods( [ 'generateUserId' ] )
            ->setConstructorArgs( [null] )
            ->getMock();

        $mock->expects($this->once())
            ->method('generateUserId')
            ->will($this->returnValue(1234));

        $mock->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $user = $mock->getByUsername('john');
        $this->assertEquals('1234', $user->get($this->object->getUserTable()->id));
        $this->assertEquals('John Doe', $user->get($this->object->getUserTable()->name));
        $this->assertEquals('john', $user->get($this->object->getUserTable()->username));
        $this->assertEquals('johndoe@gmail.com', $user->get($this->object->getUserTable()->email));
        $this->assertEquals('91DFD9DDB4198AFFC5C194CD8CE6D338FDE470E2', $user->get($this->object->getUserTable()->password));
    }

    public function testAddProperty()
    {
        // Add one property
        $this->object->addProperty($this->prefix . '2', 'city', 'Rio de Janeiro');
        $user = $this->object->getByUsername('user2');
        $this->assertEquals('Rio de Janeiro', $user->get('city'));

        // Add another property (cannot change)
        $this->object->addProperty($this->prefix . '2', 'city', 'Belo Horizonte');
        $user = $this->object->getByUsername('user2');
        $this->assertEquals('Rio de Janeiro', $user->get('city'));

        // Get Property
        $this->assertEquals(['Rio de Janeiro', 'Belo Horizonte'], $this->object->getProperty($this->prefix . '2', 'city'));

        // Add another property
        $this->object->addProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->getByUsername('user2');
        $this->assertEquals('RJ', $user->get('state'));

        // Remove Property
        $this->object->removeProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->getByUsername('user2');
        $this->assertEmpty($user->get('state'));

        // Remove Property Again
        $this->object->removeProperty($this->prefix . '2', 'city', 'Rio de Janeiro');
        $this->assertEquals(['Belo Horizonte'], $this->object->getProperty($this->prefix . '2', 'city'));

    }

    public function testRemoveUserName()
    {
        $user = $this->object->getByUsername('user1');
        $this->assertNotNull($user);

        $result = $this->object->removeUserName('user1');
        $this->assertTrue($result);

        $user = $this->object->getByUsername('user1');
        $this->assertNull($user);

    }

    public function testEditUser()
    {
        // Getting data
        $user = $this->object->getByUsername('user1');
        $this->assertEquals('User 1', $user->get($this->object->getUserTable()->name));

        // Change and Persist data
        $user->set($this->object->getUserTable()->name, 'Other name');
        $this->object->save();

        // Check if data persists
        $user = $this->object->getById($this->prefix . '1');
        $this->assertEquals('Other name', $user->get($this->object->getUserTable()->name));
    }

    public function testIsValidUser()
    {
        // User Exists!
        $user = $this->object->isValidUser('user3', 'pwd3');
        $this->assertEquals('User 3', $user->get($this->object->getUserTable()->name));

        // User Does not Exists!
        $user = $this->object->isValidUser('user55', 'pwd5');
        $this->assertNull($user);
    }

    public function testIsAdmin()
    {
        // Set the Admin Flag
        $user = $this->object->getByUsername('user3');
        $user->set($this->object->getUserTable()->admin, 'Y');
        $this->object->save();

        // Check is Admin
        $this->assertTrue($this->object->isAdmin($this->prefix . '3'));
    }

    public function testCreateAuthToken()
    {
        $user = $this->object->createAuthToken(
            'user2',
            'pwd2',
            'api.test.com',
            '1234567',
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>'tokenValue']
        );
        $token = $user->get('TOKEN');

        $dataFromToken = new \stdClass();
        $dataFromToken->tokenData = 'tokenValue';
        $dataFromToken->username = 'user2';

        $this->assertEquals(
            [
                'user' => $user,
                'data' => $dataFromToken
            ],
            $this->object->isValidToken('user2', 'api.test.com', '1234567', $token)
        );
    }

    /**
     * @expectedException \ByJG\Authenticate\Exception\NotAuthenticatedException
     */
    public function testValidateTokenWithAnotherUser()
    {
        $user = $this->object->createAuthToken(
            'user2',
            'pwd2',
            'api.test.com',
            '1234567',
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>'tokenValue']
        );
        $token = $user->get('TOKEN');

        $this->object->isValidToken('user1', 'api.test.com', '1234567', $token);
    }

    /**
     * @expectedException \ByJG\Authenticate\Exception\NotAuthenticatedException
     */
    public function testCreateAuthTokenFail_2()
    {
        $this->object->isValidToken('user1', 'api.test.com', '1234567', 'Invalid token');
    }

    public function testUserContext()
    {
        $session = new SessionContext();
        
        $this->assertFalse($session->isAuthenticated());

        $session->registerLogin(10);

        $this->assertEquals(10, $session->userInfo());
        $this->assertTrue($session->isAuthenticated());

        $session->setSessionData('property1', 'value1');
        $session->setSessionData('property2', 'value2');

        $this->assertEquals('value1', $session->getSessionData('property1'));
        $this->assertEquals('value2', $session->getSessionData('property2'));

        $session->registerLogout();

        $this->assertFalse($session->isAuthenticated());
    }

    /**
     * @expectedException \ByJG\Authenticate\Exception\NotAuthenticatedException
     */
    public function testUserContextNotActiveSession()
    {
        $session = new SessionContext();
        $this->assertEmpty($session->getSessionData('property1'));
    }

}

<?php
/**
 * Copyright (C) 2014 David Young
 *
 * Provides methods for retrieving user data from a PostgreSQL database
 */
namespace RamODev\Application\API\V1\Users\Repositories\User;
use RamODev\Application\API\V1\Users;
use RamODev\Application\API\V1\Users\Factories;
use RamODev\Application\Databases\SQL;
use RamODev\Application\Databases\SQL\Exceptions as SQLExceptions;
use RamODev\Application\Databases\SQL\PostgreSQL\QueryBuilders as PostgreSQLQueryBuilders;
use RamODev\Application\Databases\SQL\QueryBuilders as GenericQueryBuilders;
use RamODev\Application\Exceptions;
use RamODev\Application\Repositories;

class PostgreSQLRepo extends Repositories\PostgreSQLRepo implements IUserRepo
{
    /** @var Factories\IUserFactory The user factory to use when creating user objects */
    private $userFactory = null;
    /** @var PostgreSQLQueryBuilders\SelectQuery The select query used across get methods */
    private $getQuery = null;

    /**
     * @param SQL\Database $sqlDatabase The database to use for queries
     * @param Factories\IUserFactory $userFactory The user factory to use when creating user objects
     */
    public function __construct(SQL\Database $sqlDatabase, Factories\IUserFactory $userFactory)
    {
        parent::__construct($sqlDatabase);

        $this->userFactory = $userFactory;
    }

    /**
     * Adds a user to the repository
     *
     * @param Users\IUser $user The user to store in the repository
     * @return bool True if successful, otherwise false
     */
    public function add(Users\IUser &$user)
    {
        $this->sqlDatabase->startTransaction();

        try
        {
            $queryBuilder = new PostgreSQLQueryBuilders\QueryBuilder();

            // Add the user to the users table
            $userInsertQuery = $queryBuilder->insert("users.users", array("username" => $user->getUsername()));
            $this->sqlDatabase->query($userInsertQuery->getSQL(), $userInsertQuery->getParameters());

            // We'll take this opportunity to update the user's ID
            $user->setID((int)$this->sqlDatabase->getLastInsertID("users.users_id_seq"));

            // Add the user's data to the user data table
            $userDataInsertQuery = $queryBuilder->insert("users.userdata", array("userid" => $user->getID()));
            $this->write($user, $userDataInsertQuery);
            $this->log($user, Repositories\ActionTypes::ADDED);
            $this->sqlDatabase->commitTransaction();

            return true;
        }
        catch(SQLExceptions\SQLException $ex)
        {
            Exceptions\Log::write("Failed to update user: " . $ex);
            $this->sqlDatabase->rollBackTransaction();
            $user->setID(-1);
        }

        return false;
    }

    /**
     * Gets all the users in the repository
     *
     * @return array|bool The array of users if successful, otherwise false
     */
    public function getAll()
    {
        $this->buildGetQuery();

        return $this->read($this->getQuery->getSQL(), $this->getQuery->getParameters(), "createUsersFromRows", false);
    }

    /**
     * Gets the user with the input email
     *
     * @param string $email The email we're searching for
     * @return Users\IUser|bool The user that has the input email if successful, otherwise false
     */
    public function getByEmail($email)
    {
        $this->buildGetQuery();
        $this->getQuery->andWhere("LOWER(email) = :email")
            ->addNamedPlaceholderValue("email", strtolower($email));

        return $this->read($this->getQuery->getSQL(), $this->getQuery->getParameters(), "createUsersFromRows", true);
    }

    /**
     * Gets the user with the input ID
     *
     * @param int $id The ID of the user we're searching for
     * @return Users\IUser|bool The user with the input ID if successful, otherwise false
     */
    public function getByID($id)
    {
        $this->buildGetQuery();
        $this->getQuery->andWhere("id = :id")
            ->addNamedPlaceholderValue("id", $id);

        return $this->read($this->getQuery->getSQL(), $this->getQuery->getParameters(), "createUsersFromRows", true);
    }

    /**
     * Gets the user with the input username
     *
     * @param string $username The username to search for
     * @return Users\IUser|bool The user with the input username if successful, otherwise false
     */
    public function getByUsername($username)
    {
        $this->buildGetQuery();
        $this->getQuery->andWhere("LOWER(username) = :username")
            ->addNamedPlaceholderValue("username", strtolower($username));

        return $this->read($this->getQuery->getSQL(), $this->getQuery->getParameters(), "createUsersFromRows", true);
    }

    /**
     * Gets the user with the input username and hashed password
     *
     * @param string $username The username to search for
     * @param string $hashedPassword The hashed password to search for
     * @return Users\IUser|bool The user with the input username and password if successful, otherwise false
     */
    public function getByUsernameAndPassword($username, $hashedPassword)
    {
        $this->buildGetQuery();
        $this->getQuery->andWhere("LOWER(username) = :username")
            ->andWhere("password = :password")
            ->addNamedPlaceholderValues(array("username" => strtolower($username), "password" => $hashedPassword));

        return $this->read($this->getQuery->getSQL(), $this->getQuery->getParameters(), "createUsersFromRows", true);
    }

    /**
     * Updates a user in the repository
     *
     * @param Users\IUser $user The user to update in the repository
     * @return bool True if successful, otherwise false
     */
    public function update(Users\IUser &$user)
    {
        $this->sqlDatabase->startTransaction();

        try
        {
            $queryBuilder = new PostgreSQLQueryBuilders\QueryBuilder();
            $updateQuery = $queryBuilder->update("users.userdata", "", array())
                ->where("userid = ?");
            $this->write($user, $updateQuery);
            $this->log($user, Repositories\ActionTypes::UPDATED);
            $this->sqlDatabase->commitTransaction();

            return true;
        }
        catch(SQLExceptions\SQLException $ex)
        {
            Exceptions\Log::write("Failed to update user: " . $ex);
            $this->sqlDatabase->rollBackTransaction();
        }

        return false;
    }

    /**
     * Creates a list of user objects from the database results
     *
     * @param array $rows The rows of results from the query
     * @return array The list of user objects
     */
    protected function createUsersFromRows($rows)
    {
        $users = array();

        for($rowIter = 0;$rowIter < count($rows);$rowIter++)
        {
            $row = $rows[$rowIter];
            $id = $row["id"];
            $username = $row["username"];
            $password = $row["password"];
            $email = $row["email"];
            $dateCreated = new \DateTime($row["datecreated"], new \DateTimeZone("UTC"));
            $firstName = $row["firstname"];
            $lastName = $row["lastname"];

            $users[] = $this->userFactory->createUser($id, $username, $password, $email, $dateCreated, $firstName, $lastName);
        }

        return $users;
    }

    /**
     * Builds the basic get query that's common to all get methods
     */
    private function buildGetQuery()
    {
        $queryBuilder = new PostgreSQLQueryBuilders\QueryBuilder();
        $this->getQuery = $queryBuilder->select("id", "username", "password", "email", "datecreated", "firstname", "lastname")
            ->from("users.usersview");
    }

    /**
     * Logs changes in the appropriate table
     *
     * @param Users\IUser $user The user whose changes we must log
     * @param int $actionTypeID The ID of the type of action we've taken on the user
     * @throws SQLExceptions\SQLException Thrown if any of the queries fails
     */
    private function log(Users\IUser &$user, $actionTypeID)
    {
        $queryBuilder = new PostgreSQLQueryBuilders\QueryBuilder();
        $insertQuery = $queryBuilder->insert("users.userdatalog", array("userid" => $user->getID(), "actiontypeid" => $actionTypeID));
        $this->write($user, $insertQuery);
    }

    /**
     * Executes the input query for the user's various attributes
     *
     * @param Users\IUser $user The user whose data we are changing
     * @param GenericQueryBuilders\Query $query The query we'll run
     */
    private function write(Users\IUser $user, GenericQueryBuilders\Query $query)
    {
        /**
         * Each item in the array contains the array of placeholder names to their respective values
         * We can loop through them, update the query with their values, and then execute them
         */
        $placeholders = array(
            array("userdatatypeid" => UserDataTypes::EMAIL, "value" => $user->getEmail()),
            array("userdatatypeid" => UserDataTypes::PASSWORD, "value" => $user->getHashedPassword()),
            array("userdatatypeid" => UserDataTypes::FIRST_NAME, "value" => $user->getFirstName()),
            array("userdatatypeid" => UserDataTypes::LAST_NAME, "value" => $user->getLastName()),
            array("userdatatypeid" => UserDataTypes::DATE_CREATED, "value" => $user->getDateCreated()->format("Y-m-d H:i:s"))
        );

        // Run the query
        for($placeholdersIter = 0;$placeholdersIter < count($placeholders);$placeholdersIter++)
        {
            if($placeholdersIter > 0)
            {
                // We need to remove the previous placeholders
                $query->removeUnnamedPlaceHolder(count($query->getParameters()) - 1);
                $query->removeUnnamedPlaceHolder(count($query->getParameters()) - 1);
            }

            $query->addColumnValues($placeholders[$placeholdersIter]);
            $this->sqlDatabase->query($query->getSQL(), $query->getParameters());
        }
    }
} 
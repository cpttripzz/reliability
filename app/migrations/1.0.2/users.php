<?php 

use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Reference;
use Phalcon\Mvc\Model\Migration;

class UsersMigration_102 extends Migration
{

    public function up()
    {
        self::$_connection->insert(
            "users",
            array("admin", 'Hjmdj*m8j39jdK')
        );
    }
}

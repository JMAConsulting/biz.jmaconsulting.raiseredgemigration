<?php
require_once __DIR__ . '/../../raisersedgemigration.variables.php';

class CRM_RaisersEdgeMigration_SQL {

  public static $_singleton;

  protected $_connect;

  /**
   * The constructor sets search parameters and instantiate CRM_Utils_HttpClient
   */
  public function __construct() {
    require_once 'DB.php';
    $this->_connect = DB::connect(sprintf("mysql://%s:%s@%s:%s/%s", USERNAME, PASSWORD, SERVER, PORT, DATABASE));
    //$this->_connect = @mysqli_connect(SERVER, USERNAME, PASSWORD, DATABASE, PORT);
  }

  /**
   * Singleton function used to manage this object.
   *
   * @param array $searchParams
   *   Donor Perfect parameters
   *
   * @return CRM_RaisersEdgeMigration_SQL
   */
  public static function &singleton($searchParams = array()) {
    if (self::$_singleton === NULL) {
      self::$_singleton = new CRM_RaisersEdgeMigration_SQL();
    }
    return self::$_singleton;
  }

  public function query($sql) {
    $result = $this->_connect->query($sql);
    $rows = [];
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
      $rows[] = $row;
    }

    return $rows;
  }

}

<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

use Civi\Api4\UserJob;

/**
 * This class defines the DataSource interface but must be subclassed to be
 * useful.
 */
abstract class CRM_Import_DataSource {

  /**
   * @var \CRM_Core_DAO
   */
  private $queryResultObject;

  /**
   * @var int
   */
  private $limit;

  /**
   * @param int $limit
   *
   * @return CRM_Import_DataSource
   */
  public function setLimit(int $limit): CRM_Import_DataSource {
    $this->limit = $limit;
    $this->queryResultObject = NULL;
    return $this;
  }

  /**
   * @param int $offset
   *
   * @return CRM_Import_DataSource
   */
  public function setOffset(int $offset): CRM_Import_DataSource {
    $this->offset = $offset;
    $this->queryResultObject = NULL;
    return $this;
  }

  /**
   * @var int
   */
  private $offset;

  /**
   * Statuses of rows to fetch.
   *
   * @var array
   */
  private $statuses = [];

  /**
   * Current row.
   *
   * @var array
   */
  private $row;

  /**
   * @param array $statuses
   *
   * @return self
   */
  public function setStatuses(array $statuses): self {
    $this->statuses = $statuses;
    $this->queryResultObject = NULL;
    return $this;
  }

  /**
   * Class constructor.
   *
   * @param int|null $userJobID
   */
  public function __construct(int $userJobID = NULL) {
    if ($userJobID) {
      $this->setUserJobID($userJobID);
    }
  }

  /**
   * Form fields declared for this datasource.
   *
   * @var string[]
   */
  protected $submittableFields = [];

  /**
   * User job id.
   *
   * This is the primary key of the civicrm_user_job table which is used to
   * track the import.
   *
   * @var int
   */
  protected $userJobID;

  /**
   * @return int|null
   */
  public function getUserJobID(): ?int {
    return $this->userJobID;
  }

  /**
   * Set user job ID.
   *
   * @param int $userJobID
   */
  public function setUserJobID(int $userJobID): void {
    $this->userJobID = $userJobID;
  }

  /**
   * User job details.
   *
   * This is the relevant row from civicrm_user_job.
   *
   * @var array
   */
  protected $userJob;

  /**
   * Get User Job.
   *
   * API call to retrieve the userJob row.
   *
   * @return array
   *
   * @throws \API_Exception
   */
  protected function getUserJob(): array {
    if (!$this->userJob) {
      $this->userJob = UserJob::get()
        ->addWhere('id', '=', $this->getUserJobID())
        ->execute()
        ->first();
    }
    return $this->userJob;
  }

  /**
   * Get submitted value.
   *
   * Get a value submitted on the form.
   *
   * @return mixed
   *
   * @throws \API_Exception
   */
  protected function getSubmittedValue(string $valueName) {
    return $this->getUserJob()['metadata']['submitted_values'][$valueName];
  }

  /**
   * Get rows as an array.
   *
   * The array has all values.
   *
   * @param bool $nonAssociative
   *   Return as a non-associative array?
   *
   * @return array
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  public function getRows(bool $nonAssociative = TRUE): array {
    $rows = [];
    while ($this->getRow()) {
      // Historically we expect a non-associative array...
      $rows[] = $nonAssociative ? array_values($this->row) : $this->row;
    }
    $this->queryResultObject = NULL;
    return $rows;
  }

  /**
   * Get the next row.
   *
   * @return array|null
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  public function getRow(): ?array {
    if (!$this->queryResultObject) {
      $this->instantiateQueryObject();
    }
    if (!$this->queryResultObject->fetch()) {
      return NULL;
    }
    $values = $this->queryResultObject->toArray();
    /* trim whitespace around the values */
    foreach ($values as $k => $v) {
      $values[$k] = trim($v, " \t\r\n");
    }
    $this->row = $values;
    return $values;
  }

  /**
   * Get row count.
   *
   * The array has all values.
   *
   * @return int
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  public function getRowCount(array $statuses = []): int {
    $this->statuses = $statuses;
    $query = 'SELECT count(*) FROM ' . $this->getTableName() . ' ' . $this->getStatusClause();
    return CRM_Core_DAO::singleValueQuery($query);
  }

  /**
   * Get an array of column headers, if any.
   *
   * Null is returned when there are none - ie because a csv file does not
   * have an initial header row.
   *
   * This is presented to the user in the MapField screen so
   * that can see what fields they are mapping.
   *
   * @return array
   * @throws \API_Exception
   */
  public function getColumnHeaders(): array {
    return $this->getUserJob()['metadata']['DataSource']['column_headers'];
  }

  /**
   * Get an array of column headers, if any.
   *
   * Null is returned when there are none - ie because a csv file does not
   * have an initial header row.
   *
   * This is presented to the user in the MapField screen so
   * that can see what fields they are mapping.
   *
   * @return int
   * @throws \API_Exception
   */
  public function getNumberOfColumns(): int {
    return $this->getUserJob()['metadata']['DataSource']['number_of_columns'];
  }

  /**
   * Generated metadata relating to the the datasource.
   *
   * This is values that are computed within the DataSource class and
   * which are stored in the userJob metadata in the DataSource key - eg.
   *
   * ['table_name' => $]
   *
   * Will be in the user_job.metadata field encoded into the json like
   *
   * `{'DataSource' : ['table_name' => $], 'submitted_values' : .....}`
   *
   * @var array
   */
  protected $dataSourceMetadata = [];

  /**
   * Get metadata about the datasource.
   *
   * @return array
   *
   * @throws \API_Exception
   */
  public function getDataSourceMetadata(): array {
    if (!$this->dataSourceMetadata && $this->getUserJobID()) {
      $this->dataSourceMetadata = $this->getUserJob()['metadata']['DataSource'];
    }

    return $this->dataSourceMetadata;
  }

  /**
   * Get the table name for the datajob.
   *
   * @return string|null
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  protected function getTableName(): ?string {
    // The old name is still stored...
    $tableName = $this->getDataSourceMetadata()['table_name'];
    if (!$tableName) {
      return NULL;
    }
    if (strpos($tableName, 'civicrm_tmp_') !== 0
      || !CRM_Utils_Rule::alphanumeric($tableName)) {
      // The table name is generated and stored by code, not users so it
      // should be safe - but a check seems prudent all the same.
      throw new CRM_Core_Exception('Table cannot be deleted');
    }
    return $tableName;
  }

  /**
   * Get the fields declared for this datasource.
   *
   * @return string[]
   */
  public function getSubmittableFields(): array {
    return $this->submittableFields;
  }

  /**
   * Provides information about the data source.
   *
   * @return array
   *   Description of this data source, including:
   *   - title: string, translated, required
   *   - permissions: array, optional
   *
   */
  abstract public function getInfo();

  /**
   * This is function is called by the form object to get the DataSource's form snippet.
   *
   * It should add all fields necessary to get the data uploaded to the temporary table in the DB.
   *
   * @param CRM_Core_Form $form
   */
  abstract public function buildQuickForm(&$form);

  /**
   * Initialize the datasource, based on the submitted values stored in the user job.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  public function initialize(): void {

  }

  /**
   * Determine if the current user has access to this data source.
   *
   * @return bool
   */
  public function checkPermission() {
    $info = $this->getInfo();
    return empty($info['permissions']) || CRM_Core_Permission::check($info['permissions']);
  }

  /**
   * @param string $key
   * @param array $data
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function updateUserJobMetadata(string $key, array $data): void {
    $metaData = array_merge(
      $this->getUserJob()['metadata'],
      [$key => $data]
    );
    UserJob::update(FALSE)
      ->addWhere('id', '=', $this->getUserJobID())
      ->setValues(['metadata' => $metaData])
      ->execute();
    $this->userJob['metadata'] = $metaData;
  }

  /**
   * Purge any datasource related assets when the datasource is dropped.
   *
   * This is the datasource's chance to delete any tables etc that it created
   * which will now not be used.
   *
   * @param array $newParams
   *   If the dataSource is being updated to another variant of the same
   *   class (eg. the csv upload was set to no column headers and they
   *   have resubmitted WITH skipColumnHeader (first row is a header) then
   *   the dataSource is still CSV and the params for the new intance
   *   are passed in. When changing from csv to SQL (for example) newParams is
   *   empty.
   *
   * @return array
   *   The details to update the DataSource key in the userJob metadata to.
   *   Generally and empty array but it the datasource decided (for example)
   *   that the table it created earlier is still consistent with the new params
   *   then it might decided not to drop the table and would want to retain
   *   some metadata.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   *
   * @noinspection PhpUnusedParameterInspection
   */
  public function purge(array $newParams = []) :array {
    // The old name is still stored...
    $oldTableName = $this->getTableName();
    if ($oldTableName) {
      CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS ' . $oldTableName);
    }
    return [];
  }

  /**
   * Add a status columns to the import table.
   *
   * We add
   *  _id - primary key
   *  _status
   *  _statusMsg
   *
   * Note that
   * 1) the use of the preceding underscore has 2 purposes - it avoids clashing
   *   with an id field (code comments from 14 years ago suggest perhaps there
   *   could be cases where it still clashes but time didn't tell in this case)
   * 2) the show fields query used to get the column names excluded the
   *   administrative fields, relying on this convention.
   * 3) we have the capitalisation on _statusMsg - @todo change to _status_message
   *
   * @param string $tableName
   */
  protected function addTrackingFieldsToTable(string $tableName): void {
    CRM_Core_DAO::executeQuery("
     ALTER TABLE $tableName
       ADD COLUMN _entity_id INT,
       ADD COLUMN _related_entity_ids JSON,
       ADD COLUMN _status VARCHAR(32) DEFAULT 'NEW' NOT NULL,
       ADD COLUMN _status_message TEXT,
       ADD COLUMN _id INT PRIMARY KEY NOT NULL AUTO_INCREMENT"
    );
  }

  /**
   * Has the import job completed.
   *
   * @return bool
   *   True if no rows remain to be imported.
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  public function isCompleted(): bool {
    return (bool) $this->getRowCount(['new']);
  }

  /**
   * Update the status of the import row to reflect the processing outcome.
   *
   * @param int $id
   * @param string $status
   * @param string $message
   * @param int|null $entityID
   *   Optional created entity ID
   * @param array $relatedEntityIDs
   *   Optional array e.g ['related_contact' => 4]
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  public function updateStatus(int $id, string $status, string $message, ? int $entityID = NULL, array $relatedEntityIDs = []): void {
    $sql = 'UPDATE ' . $this->getTableName() . ' SET _status = %1, _status_message = %2 ';
    $params = [1 => [$status, 'String'], 2 => [$message, 'String']];
    if ($entityID) {
      $sql .= ', _entity_id = %3';
      $params[3] = [$entityID, 'Integer'];
    }
    if ($relatedEntityIDs) {
      $sql .= ', _related_entities = %4';
      $params[4] = [json_encode($relatedEntityIDs), 'String'];
    }
    CRM_Core_DAO::executeQuery($sql . ' WHERE _id = ' . $id, $params);
  }

  /**
   *
   * @throws \API_Exception
   * @throws \CRM_Core_Exception
   */
  private function instantiateQueryObject(): void {
    $query = 'SELECT * FROM ' . $this->getTableName() . ' ' . $this->getStatusClause();
    if ($this->limit) {
      $query .= ' LIMIT ' . $this->limit . ($this->offset ? (' OFFSET ' . $this->offset) : NULL);
    }
    $this->queryResultObject = CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Get the mapping of constants to database status codes.
   *
   * @return array[]
   */
  protected function getStatusMapping(): array {
    return [
      CRM_Import_Parser::VALID => ['imported', 'new'],
      CRM_Import_Parser::ERROR => ['error', 'invalid'],
      CRM_Import_Parser::DUPLICATE => ['duplicate'],
      CRM_Import_Parser::NO_MATCH => ['invalid_no_match'],
      CRM_Import_Parser::UNPARSED_ADDRESS_WARNING => ['warning_unparsed_address'],
      'new' => ['new'],
    ];
  }

  /**
   * Get the status filter clause.
   *
   * @return string
   */
  private function getStatusClause(): string {
    if (!empty($this->statuses)) {
      $statuses = [];
      foreach ($this->statuses as $status) {
        foreach ($this->getStatusMapping()[$status] as $statusName) {
          $statuses[] = '"' . $statusName . '"';
        }
      }
      return ' WHERE _status IN (' . implode(',', $statuses) . ')';
    }
    return '';
  }

}

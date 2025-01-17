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

/**
 * This class summarizes the import results
 */
class CRM_Event_Import_Form_Summary extends CRM_Import_Form_Summary {

  /**
   * Set variables up before form is built.
   *
   * @return void
   */
  public function preProcess() {
    // set the error message path to display
    $this->assign('errorFile', $this->get('errorFile'));

    $totalRowCount = $this->get('totalRowCount');
    $relatedCount = $this->get('relatedCount');
    $totalRowCount += $relatedCount;
    $this->set('totalRowCount', $totalRowCount);

    $invalidRowCount = $this->get('invalidRowCount');
    $duplicateRowCount = $this->get('duplicateRowCount');
    $onDuplicate = $this->get('onDuplicate');
    if ($duplicateRowCount > 0) {
      $urlParams = 'type=' . CRM_Import_Parser::DUPLICATE . '&parser=CRM_Event_Import_Parser_Participant';
      $this->set('downloadDuplicateRecordsUrl', CRM_Utils_System::url('civicrm/export', $urlParams));
    }
    else {
      $duplicateRowCount = 0;
      $this->set('duplicateRowCount', $duplicateRowCount);
    }

    $this->assign('dupeError', FALSE);

    if ($onDuplicate == CRM_Import_Parser::DUPLICATE_UPDATE) {
      $dupeActionString = ts('These records have been updated with the imported data.');
    }
    elseif ($onDuplicate == CRM_Import_Parser::DUPLICATE_FILL) {
      $dupeActionString = ts('These records have been filled in with the imported data.');
    }
    else {
      /* Skip by default */

      $dupeActionString = ts('These records have not been imported.');

      $this->assign('dupeError', TRUE);

      /* only subtract dupes from successful import if we're skipping */

      $this->set('validRowCount', $totalRowCount - $invalidRowCount -
        $duplicateRowCount
      );
    }
    $this->assign('dupeActionString', $dupeActionString);

    $properties = [
      'totalRowCount',
      'validRowCount',
      'invalidRowCount',
      'downloadErrorRecordsUrl',
      'duplicateRowCount',
      'downloadDuplicateRecordsUrl',
      'groupAdditions',
    ];
    foreach ($properties as $property) {
      $this->assign($property, $this->get($property));
    }
  }

}

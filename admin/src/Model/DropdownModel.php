<?php

namespace ETE\Component\EventTableEdit\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Versioning\VersionableModelTrait;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;

/**
 * Dropdown model.
 *
 * @since  1.6
 */
class DropdownModel extends AdminModel
{
	use VersionableModelTrait;

	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $text_prefix = 'com_eventtableedit_dropdown';

	/**
	 * The type alias for this content type.
	 *
	 * @var    string
	 * @since  3.2
	 */
	public $typeAlias = 'com_eventtableedit.dropdown';


	/**
	 * Allowed batch commands
	 *
	 * @var  array
	 */
	protected $batch_commands = array(
		'client_id'   => 'batchClient',
		'language_id' => 'batchLanguage'
	);

	/**
	 * Batch client changes for a group of etetables.
	 *
	 * @param   string  $value     The new value matching a client.
	 * @param   array   $pks       An array of row IDs.
	 * @param   array   $contexts  An array of item contexts.
	 *
	 * @return  boolean  True if successful, false otherwise and internal error is set.
	 *
	 * @since   2.5
	 */
	protected function batchClient($value, $pks, $contexts)
	{
		// Set the variables
		$user = Factory::getUser();

		$table = $this->getTable();

		foreach ($pks as $pk)
		{
			if (!$user->authorise('core.edit', $contexts[$pk]))
			{
				$this->setError(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_EDIT'));

				return false;
			}

			$table->reset();
			$table->load($pk);
			$table->cid = (int) $value;

			if (!$table->store())
			{
				$this->setError($table->getError());

				return false;
			}
		}

		// Clean the cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param   object  $record  A record object.
	 *
	 * @return  boolean  True if allowed to delete the record. Defaults to the permission set in the component.
	 *
	 * @since   1.6
	 */
	protected function canDelete($record)
	{
		if (empty($record->id))
		{
			return false;
		}

		return parent::canDelete($record);
	}
	
	
	function delete(&$pks)
	{
		$table = $this->getTable();
		foreach ($pks as $i => $pk)
		{
			if ($table->load($pk))
			{
				
				if ($this->canDelete($table))
				{
					$this->deleteDropdowns($pk);
				}
			}
		}
		
		return parent::delete($pks);
	}

	/**
	 * A method to preprocess generating a new title in order to allow tables with alternative names
	 * for alias and title to use the batch move and copy methods
	 *
	 * @param   integer  $categoryId  The target category id
	 * @param   Table    $table       The JTable within which move or copy is taking place
	 *
	 * @return  void
	 *
	 * @since   3.8.12
	 */
	public function generateTitle($categoryId, $table)
	{
		// Alter the title & alias
		$data = $this->generateNewTitle($categoryId, $table->alias, $table->name);
		$table->name = $data['0'];
		$table->alias = $data['1'];
	}

	/**
	 * Method to test whether a record can have its state changed.
	 *
	 * @param   object  $record  A record object.
	 *
	 * @return  boolean  True if allowed to change the state of the record. Defaults to the permission set in the component.
	 *
	 * @since   1.6
	 */
	protected function canEditState($record)
	{
		// Default to component settings if category not known.
		return parent::canEditState($record);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      Data for the form. [optional]
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not. [optional]
	 *
	 * @return  Form|boolean  A Form object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		
		// Get the form.
		$form = $this->loadForm('com_eventtableedit.dropdown', 'dropdown', array('control' => 'jform', 'load_data' => $loadData));
		
		if (empty($form))
		{
			return false;
		}

		// Modify the form based on access controls.
		if (!$this->canEditState((object) $data))
		{
			// Disable fields for display.
			//$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('publish_up', 'disabled', 'true');
			$form->setFieldAttribute('publish_down', 'disabled', 'true');
			$form->setFieldAttribute('state', 'disabled', 'true');
			$form->setFieldAttribute('sticky', 'disabled', 'true');

			// Disable fields while saving.
			// The controller has already verified this is a record you can edit.
			//$form->setFieldAttribute('ordering', 'filter', 'unset');
			$form->setFieldAttribute('publish_up', 'filter', 'unset');
			$form->setFieldAttribute('publish_down', 'filter', 'unset');
			$form->setFieldAttribute('state', 'filter', 'unset');
			$form->setFieldAttribute('sticky', 'filter', 'unset');
		}

		// Don't allow to change the created_by user if not allowed to access com_users.
		if (!Factory::getUser()->authorise('core.manage', 'com_users'))
		{
			$form->setFieldAttribute('created_by', 'filter', 'unset');
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_eventtableedit.edit.dropdown.data', array());

		if (empty($data))
		{
			
			$data = $this->getItem();
			
		}

		$this->preprocessData('com_eventtableedit.dropdown', $data);
		$data->name = str_replace('dpd_','',$data->name);
		return $data;
	}

	/**
	 * Method to stick records.
	 *
	 * @param   array    $pks    The ids of the items to publish.
	 * @param   integer  $value  The value of the published state
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	public function stick(&$pks, $value = 1)
	{
		/** @var \Joomla\Component\etetables\Administrator\Table\etetableTable $table */
		$table = $this->getTable();
		$pks   = (array) $pks;

		// Access checks.
		foreach ($pks as $i => $pk)
		{
			if ($table->load($pk))
			{
				if (!$this->canEditState($table))
				{
					// Prune items that you can't change.
					unset($pks[$i]);
					Factory::getApplication()->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');
				}
			}
		}

		// Attempt to change the state of the records.
		if (!$table->stick($pks, $value, Factory::getUser()->id))
		{
			$this->setError($table->getError());

			return false;
		}

		return true;
	}

	/**
	 * A protected method to get a set of ordering conditions.
	 *
	 * @param   Table  $table  A record object.
	 *
	 * @return  array  An array of conditions to add to ordering queries.
	 *
	 * @since   1.6
	 */
	protected function getReorderConditions($table)
	{
		return [
			$this->_db->quoteName('catid') . ' = ' . (int) $table->catid,
			$this->_db->quoteName('state') . ' >= 0',
		];
	}

	/**
	 * Prepare and sanitise the table prior to saving.
	 *
	 * @param   Table  $table  A Table object.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function prepareTable($table)
	{
		$date = Factory::getDate();
		$user = Factory::getUser();

		if (empty($table->id))
		{
			// Set the values
			$table->created    = $date->toSql();
			$table->created_by = $user->id;		

			
		}
		else
		{
			
			// Set the values
			$table->modified    = $date->toSql();
			$table->modified_by = $user->id;
		}

		// Increment the content version number.
		//$table->version++;
	}

	/**
	 * Allows preprocessing of the Form object.
	 *
	 * @param   Form    $form   The form object
	 * @param   array   $data   The data to be merged into the form object
	 * @param   string  $group  The plugin group to be executed
	 *
	 * @return  void
	 *
	 * @since    3.6.1
	 */
	protected function preprocessForm(Form $form, $data, $group = 'content')
	{
		if ($this->canCreateCategory())
		{
			$form->setFieldAttribute('catid', 'allowAdd', 'true');

			// Add a prefix for categories created on the fly.
			$form->setFieldAttribute('catid', 'customPrefix', '#new#');
		}

		parent::preprocessForm($form, $data, $group);
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	public function save($data)
	{
		$input = Factory::getApplication()->input;
		
		

		
		// Alter the name for save as copy
		if ($input->get('task') == 'save2copy')
		{
			/** @var \Joomla\Component\etetables\Administrator\Table\etetableTable $origTable */
			$origTable = clone $this->getTable();
			$origTable->load($input->getInt('id'));

			if ($data['name'] == $origTable->name)
			{
				list($name, $alias) = $this->generateNewTitle($data['catid'], $data['alias'], $data['name']);
				$data['name']       = $name;
				$data['alias']      = $alias;
			}
			else
			{
				if ($data['alias'] == $origTable->alias)
				{
					$data['alias'] = '';
				}
			}

			$data['state'] = 0;
		}
		
		$data['name'] = 'dpd_'.$data['name'];
		
		$date = Factory::getDate();
		$user = Factory::getUser();
		
		//$data['checked_out'] = $user->id;
		//$data['checked_out_time'] = $date->toSql();
		
		parent::save($data);
		
		$this->saveSingleDropdowns();
		
		return true;
	}
	
	
	private function saveSingleDropdowns()
    {
        // Initialise variables;
        $db = Factory::getDbo();
        $input = Factory::getApplication()->input;
	
        $name = $input->get('dropdowns');
		
		if(empty($name)){
			return;
		}
		
        $dropdown_id = (int) $this->getState($this->getName().'.id');
		
        // Delete old dropdowns
        $query = 'DELETE FROM #__eventtableedit_dropdown'.
                 ' WHERE dropdown_id = '.$dropdown_id;
        $db->setQuery($query);
        $db->execute();
		
		for ($a = 0; $a < count($name); ++$a) {
			
			$query = $db->getQuery(true);
			$columns = array('dropdown_id', 'name');
			$values = array($dropdown_id, $db->quote($name[$a]));
			
			$query->insert($db->quoteName('#__eventtableedit_dropdown'))->columns($db->quoteName($columns))->values(implode(',', $values));
			$db->setQuery($query);
			$db->execute();

        }
    }

	/**
	 * Is the user allowed to create an on the fly category?
	 *
	 * @return  boolean
	 *
	 * @since   3.6.1
	 */
	private function canCreateCategory()
	{
		return Factory::getUser()->authorise('core.create', 'com_eventtableedit');
	}
	
	
	function getDropdown(){
		if(!$this->DropdownId){
			return [];
		}
		$db = Factory::getDbo();
		$query = 'SELECT * FROM #__eventtableedit_dropdown'.
                 ' WHERE dropdown_id = '.$this->DropdownId . ' ORDER BY `id`';
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
	function deleteDropdowns($dropdown_id){
		$db = Factory::getDbo();
		$query = 'DELETE FROM #__eventtableedit_dropdown'.
                 ' WHERE dropdown_id = '.$dropdown_id;
        $db->setQuery($query);
        $db->execute();
	}
}

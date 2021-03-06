<?php defined('BASEPATH') || exit('No direct script access allowed');

$controller_name_lower = strtolower($controller_name);
$primary_key_field = set_value("primary_key_field");

//------------------------------------------------------------------------------
// Index
//------------------------------------------------------------------------------

$indexArgs = '';
$indexStartup = '';
$indexDelete = '';
$indexPaginationUri = '';
$indexPagination = '';
$indexFind = '';
$indexToolbarTitle = '';

if ($db_required != '') {
    // Public controller
    if ($controller_name_lower == $module_name_lower) {
        // Setup paging
        if ($usePagination) {
            $indexPaginationUri = "\$pagerUriSegment = 3;
        \$pagerBaseUrl = site_url('{$module_name_lower}/index') . '/';";
        }

        // Filter out soft deletes
        if ($useSoftDeletes) {
            $indexFind = <<<EOT

        // Don't display soft-deleted records
        \$this->{$module_name_lower}_model->where(\$this->{$module_name_lower}_model->get_deleted_field(), 0);
EOT;
        }
    } else {
    // Admin controllers
		$indexStartup = <<<EOT
		if (\$this->auth->is_logged_in() === false) {
    		Template::redirect('/admin/login');
 		}
EOT;
        $indexDelete = <<<EOT
		// Deleting anything?
		if (isset(\$_POST['delete'])) {
            //$this->auth->restrict($this->permissionDelete);
			\$checked = \$this->input->post('checked');
			if (is_array(\$checked) && count(\$checked)) {

                // If any of the deletions fail, set the result to false, so
                // failure message is set if any of the attempts fail, not just
                // the last attempt

				\$result = true;
				foreach (\$checked as \$pid) {
					\$deleted = \$this->{$module_name_lower}_model->delete(\$pid);
                    if (\$deleted == false) {
                        \$result = false;
                    }
				}
				if (\$result) {
					Template::set_message(count(\$checked) . ' ' . lang('{$module_name_lower}_delete_success'), 'success');
				} else {
					Template::set_message(lang('{$module_name_lower}_delete_failure') . \$this->{$module_name_lower}_model->error, 'error');
				}
			}
		}
EOT;

        // Setup paging
        if ($usePagination) {
            $indexPaginationUri = <<<EOT
		\$pagerUriSegment = 3;
        \$pagerBaseUrl = site_url('/{$controller_name_lower}/{$module_name_lower}/index') . '/';
EOT;
        }
    }

    // All controllers

    // Finish setup of paging
    if ($usePagination) {
        $indexArgs = "\$offset = 0";
        $indexPagination = <<<EOT
       \$pager = array(
            'base_url'          => \$pagerBaseUrl,
            'total_rows'        => \$this->{$module_name_lower}_model->count_all(),
            'per_page'          => \$this->config->item('per_page'),
            'uri_segment'       => \$pagerUriSegment,
            'num_links'         => 9,
            'use_page_numbers'  => FALSE  
        );
        
        \$this->load->library('pagination');
        \$this->pagination->initialize(\$pager);
        \$this->{$module_name_lower}_model->limit(\$limit, \$offset);
        \$data['total']          = \$pager['total_rows'];
        \$data['pagination']     = \$this->pagination->create_links();
        \$data['number']         = (int)\$this->uri->segment(\$pagerUriSegment) + 1;

EOT;
    }

    // Result of find_all() will be filtered based on paging, if used, as well
    // as where clause to filter out soft-deleted fields in public index
    $indexFind .= <<<EOT
		\$records = \$this->{$module_name_lower}_model->find_all();

		Template::set('records', \$records);
EOT;
}

//------------------------------------------------------------------------------
// Create
//------------------------------------------------------------------------------

$createSave = '';
$editSave = '';
$editDelete = '';
$editFind = '';

if ($db_required != '') {
    $createSave = "
		if (isset(\$_POST['save'])) {
			if (\$insert_id = \$this->save_{$module_name_lower}()) {
				log_activity(\$this->auth->user_id(), lang('{$module_name_lower}_act_create_record') . ': ' . \$insert_id . ' : ' . \$this->input->ip_address(), '{$module_name_lower}');
				Template::set_message(lang('{$module_name_lower}_create_success'), 'success');

				redirect(SITE_AREA . '/{$controller_name}/{$module_name_lower}');
			}

            // Not validation error
			if ( ! empty(\$this->{$module_name_lower}_model->error)) {
				Template::set_message(lang('{$module_name_lower}_create_failure') . \$this->{$module_name_lower}_model->error, 'error');
            }
		}";

    $editFind = "
        Template::set('{$module_name_lower}', \$this->{$module_name_lower}_model->find(\$id));";

    $editSave = "
		if (isset(\$_POST['save'])) {
			\$this->auth->restrict(\$this->permissionEdit);

			if (\$this->save_{$module_name_lower}('update', \$id)) {
				log_activity(\$this->auth->user_id(), lang('{$module_name_lower}_act_edit_record') . ': ' . \$id . ' : ' . \$this->input->ip_address(), '{$module_name_lower}');
				Template::set_message(lang('{$module_name_lower}_edit_success'), 'success');
				redirect(SITE_AREA . '/{$controller_name}/{$module_name_lower}');
			}

            // Not validation error
            if ( ! empty(\$this->{$module_name_lower}_model->error)) {
                Template::set_message(lang('{$module_name_lower}_edit_failure') . \$this->{$module_name_lower}_model->error, 'error');
			}
		}";

	if (in_array('delete', $action_names)) {
        $editDelete = "
		elseif (isset(\$_POST['delete'])) {
			\$this->auth->restrict(\$this->permissionDelete);

			if (\$this->{$module_name_lower}_model->delete(\$id)) {
				log_activity(\$this->auth->user_id(), lang('{$module_name_lower}_act_delete_record') . ': ' . \$id . ' : ' . \$this->input->ip_address(), '{$module_name_lower}');
				Template::set_message(lang('{$module_name_lower}_delete_success'), 'success');

				redirect(SITE_AREA . '/{$controller_name}/{$module_name_lower}');
			}

            Template::set_message(lang('{$module_name_lower}_delete_failure') . \$this->{$module_name_lower}_model->error, 'error');
		}";
	}
}

$mb_create = "
    /**
	 * Create a {$module_name} object.
	 *
	 * @return void
	 */
	public function create()
	{
		\$this->auth->restrict(\$this->permissionCreate);
        {$createSave}

		Template::set('toolbar_title', lang('{$module_name_lower}_action_create'));

		Template::render();
	}";

//------------------------------------------------------------------------------
// Edit
//------------------------------------------------------------------------------

$mb_edit = "
	/**
	 * Allows editing of {$module_name} data.
	 *
	 * @return void
	 */
	public function edit()
	{
		\$id = \$this->uri->segment(5);
		if (empty(\$id)) {
			Template::set_message(lang('{$module_name_lower}_invalid_id'), 'error');

			redirect(SITE_AREA . '/{$controller_name}/{$module_name_lower}');
		}
        {$editSave}
        {$editDelete}
        {$editFind}

		Template::set('toolbar_title', lang('{$module_name_lower}_edit_heading'));
		Template::render();
	}";

//------------------------------------------------------------------------------
// Save
//------------------------------------------------------------------------------

$mb_save = "

	//--------------------------------------------------------------------
	// !PRIVATE METHODS
	//--------------------------------------------------------------------

	/**
	 * Save the data.
	 *
	 * @param string \$type Either 'insert' or 'update'.
	 * @param int	 \$id	The ID of the record to update, ignored on inserts.
	 *
	 * @return bool|int An int ID for successful inserts, true for successful
     * updates, else false.
	 */
	private function save_{$module_name_lower}(\$type = 'insert', \$id = 0)
	{
		if (\$type == 'update') {
			\$_POST['{$primary_key_field}'] = \$id;
		}

        // Validate the data
        \$this->form_validation->set_rules(\$this->{$module_name_lower}_model->get_validation_rules());
        if (\$this->form_validation->run() === false) {
            return false;
        }

		// Make sure we only pass in the fields we want
		{save_data_array}

        \$return = false;
		if (\$type == 'insert') {
			\$id = \$this->{$module_name_lower}_model->insert(\$data);

			if (is_numeric(\$id)) {
				\$return = \$id;
			}
		} elseif (\$type == 'update') {
			\$return = \$this->{$module_name_lower}_model->update(\$id, \$data);
		}

		return \$return;
	}";

//--------------------------------------------------------------------
// Constructor
//--------------------------------------------------------------------
$constructorExtras = '';
$date_included     = false;
$datetime_included = false;
$textarea_included = false;

$jQueryUI = "
			Assets::add_css('flick/jquery-ui-1.8.13.custom.css');
			Assets::add_js('jquery-ui-1.8.13.min.js');";

for ($counter = 1; $field_total >= $counter; $counter++) {
	$db_field_type = set_value("db_field_type$counter");
	$view_datepicker = '';

	if ($db_field_type != null) {
		if ($db_field_type == 'DATE' && $date_included === false) {
			$constructorExtras .= $jQueryUI;
			$date_included = true;
		} elseif ($db_field_type == 'DATETIME' && $datetime_included === false) {
			// If a date field hasn't been included already then add in the jquery ui files
			if ($date_included === false) {
				$constructorExtras .= $jQueryUI;
                $date_included = true;
			}

			$constructorExtras .= "
			Assets::add_css('jquery-ui-timepicker.css');
			Assets::add_js('jquery-ui-timepicker-addon.js');";
			$datetime_included = true;
		} elseif (in_array($db_field_type, $textTypes)
			&& $textarea_included === false
			&& ! empty($textarea_editor)
		) {
			if ($textarea_editor == 'ckeditor') {
				$constructorExtras .= "
			Assets::add_js(Template::theme_url('js/editors/ckeditor/ckeditor.js'));";
			} elseif ($textarea_editor == 'markitup') {
				$constructorExtras .= "
			Assets::add_css(Template::theme_url('js/editors/markitup/skins/markitup/style.css'));
			Assets::add_css(Template::theme_url('js/editors/markitup/sets/default/style.css'));

			Assets::add_js(Template::theme_url('js/editors/markitup/jquery.markitup.js'));
			Assets::add_js(Template::theme_url('js/editors/markitup/sets/default/set.js'));";
			} elseif ($textarea_editor == 'tinymce') {
				$constructorExtras .= "
			Assets::add_js(Template::theme_url('js/editors/tiny_mce/tiny_mce.js'));
			Assets::add_js(Template::theme_url('js/editors/tiny_mce/tiny_mce_init.js'));";
			} 

			$textarea_included = true;
		}
	}
}

$constructorRestrict = '';
$loadModel = '';
$baseClass = 'Front_Controller';

// Is this an admin area controller?
if ($controller_name_lower != $module_name_lower) {
    $baseClass = 'Admin_Controller';
    $constructorRestrict = <<<EOT
 \$this->load->library('securinator/Auth');
EOT;
	$constructorExtras .= <<<EOT
 Template::set_theme('backend');
EOT;

    // If the form error delimiters have been passed, set them in the constructor.
    if (! empty($form_error_delimiters)
        && isset($form_error_delimiters[0])
        && isset($form_error_delimiters[1])
    ) {
        $constructorExtras .= <<<EOT
 \$this->form_validation->set_error_delimiters("{$form_error_delimiters[0]}", "{$form_error_delimiters[1]}");
EOT;
    }
}

if ($db_required != '') {
    $loadModel = <<<EOT
 \$this->load->model('{$module_name_lower}/{$module_name_lower}_model');
EOT;
}

$body = '';

//--------------------------------------------------------------------
// Check whether this is the front controller
if ($controller_name_lower != $module_name_lower) {
	// Create
	if (in_array('create', $action_names)) {
		$body .= $mb_create;
		$body = str_replace('{create_permission}', preg_replace("/[ -]/", "_", ucfirst($module_name)) . '.' . ucfirst($controller_name) . '.Create', $body);
	}

	//--------------------------------------------------------------------
	// Edit
	if (in_array('edit', $action_names)) {
		$body .= $mb_edit;
		$body = str_replace('{edit_permission}', preg_replace("/[ -]/", "_", ucfirst($module_name)) . '.' . ucfirst($controller_name) . '.Edit', $body);
		$body = str_replace('{delete_permission}', preg_replace("/[ -]/", "_", ucfirst($module_name)) . '.' . ucfirst($controller_name) . '.Delete', $body);
	}

	//--------------------------------------------------------------------
	// Save
	if ($db_required != '') {
		$body .= $mb_save;
	}

	$save_data_array = "
		\$data = \$this->{$module_name_lower}_model->prep_data(\$this->input->post());

        // Additional handling for default values should be added below,
        // or in the model's prep_data() method
        ";

	for ($counter = 1; $field_total >= $counter; $counter++) {
		// Only build on fields that have data entered.
		if (set_value("view_field_label$counter") == null) {
			continue; 	// move onto next iteration of the loop
		}

		// Set this variable as it will be used to place the comma after the last item to build the insert db array
		$last_field = $counter;
		if ($db_required == 'new' && $table_as_field_prefix === true) {
			$field_name = $module_name_lower . '_' . set_value("view_field_name$counter");
		} else {
			$field_name = set_value("view_field_name$counter");
		}

		// Setup the data array for saving to the db
		// Set defaults for certain field types
		switch (set_value("db_field_type$counter")) {
			case 'DATE':
				$save_data_array .= "\n\t\t\$data['{$field_name}']\t= \$this->input->post('{$field_name}') ? \$this->input->post('{$field_name}') : '0000-00-00';";
				break;

			case 'DATETIME':
				$save_data_array .= "\n\t\t\$data['{$field_name}']\t= \$this->input->post('{$field_name}') ? \$this->input->post('{$field_name}') : '0000-00-00 00:00:00';";
				break;

			default:
                // No need to handle fields for which defaults are not set,
                // the model's prep_data() method should take care of it.
				break;
		}
	}

	$body = str_replace('{save_data_array}', $save_data_array, $body);
}

//--------------------------------------------------------------------
// !BUILD THE CLASS
//--------------------------------------------------------------------


$permissionModuleName = ucfirst($module_name_lower);
$permissionControllerName = ucfirst($controller_name);
$controller_name = ucwords($controller_name);

echo <<<EOT
<?php defined('BASEPATH') || exit('No direct script access allowed');

/**
 * {$controller_name} controller
 * @created on : 
 * @author :
 * Copyright {year}
 *
 */
class {$controller_name} extends {$baseClass}
{
    protected \$permissionCreate = '{$permissionModuleName}.{$permissionControllerName}.Create';
    protected \$permissionDelete = '{$permissionModuleName}.{$permissionControllerName}.Delete';
    protected \$permissionEdit   = '{$permissionModuleName}.{$permissionControllerName}.Edit';
    protected \$permissionView   = '{$permissionModuleName}.{$permissionControllerName}.View';

    /**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		{$constructorRestrict}{$loadModel}
        \$this->lang->load('{$module_name_lower}');
		{$constructorExtras}
	}

	/**
	 * Display a list of {$module_name} data.
	 *
	 * @return void
	 */
	public function index({$indexArgs})
	{
		\$data = array();
        {$indexStartup}
		{$indexDelete}
        {$indexPaginationUri}
        {$indexPagination}
        {$indexFind}
        {$indexToolbarTitle}
        foreach( \$data as \$key => \$value )
            Template::set(\$key, \$value);
		Template::render();
	}
    {$body}
}
EOT;

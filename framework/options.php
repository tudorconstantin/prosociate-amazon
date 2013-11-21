<?php

class SoflyyOptionsPage {

	var $title, $slug;
        
        public $pageTitle;

	var $fields = array();

	function __construct($title, $slug, $parent_slug, $pageTitle = '') {
		$this->title = $title;
		$this->slug = $slug;
                
                                    // if no page title then use title 
                                   if( empty($pageTitle) )
                                   {
                                       $this->pageTitle = $title;
                                   }
                                   else
                                   {
                                       $this->pageTitle = $pageTitle;
                                   }

		add_submenu_page($parent_slug, $title, $title, 'manage_options', $slug, array($this, 'page'));

	}

	function add_field($title, $slug, $type='text', $description='', $values = null) {

		$slug = $this->slug.'-'.$slug;

		$this->fields[] = new SoflyyField($title, $slug, $type, $description, $values);

		register_setting($this->slug, $slug);

	}

	function page() {

		?>

		<div class="wrap">
		<h2><?php echo $this->pageTitle; ?></h2>
        <h2 class="nav-tab-wrapper">
            <a href="#" id="tabs-general-settings-link" class="nav-tab nav-tab-active">General</a>
            <a href="#" id="tabs-compliance-settings-link" class="nav-tab">Compliance</a>
        </h2>
		<form method="post" action="options.php">
            <div id='tabs-general-settings'>
		<?php settings_fields($this->slug); ?>
		<table class="form-table">

		<?php
		if ($this->fields) {
			foreach ($this->fields as $field) {
                $field->output();
	    	}
		} else {
			throw new Exception("Empty field list.");
		}

		?>
		</table>
                </div> <!-- for tabs -->

        <?php do_settings_sections( 'dm-pros-sections' ); ?>
		<?php submit_button(); ?>
		</form>
		</div>

		<?php
	}


}




class SoflyyField {

	var $title, $slug, $type, $description;

	var $values = array();

	function __construct($title, $slug, $type = 'text', $description = '', $values = null) {

		$this->title = $title;
		$this->slug = $slug;
		$this->type = $type;
		$this->description = $description;
		$this->values = $values;

	}


	function output() {

		$opt_val = get_option($this->slug);

		?>
		<tr valign="top"><th scope="row"><label for="<?php echo $this->slug; ?>"><?php echo $this->title; ?></label></th>
		<td>
		<?php

		if ($this->type == 'text') {
			?>
			<input name="<?php echo $this->slug; ?>" type="text" id="<?php echo $this->slug; ?>" value="<?php form_option($this->slug); ?>" class="regular-text" />
			<?php
		} else if ($this->type == 'select') {
			?>
			<select name="<?php echo $this->slug; ?>" id="<?php echo $this->slug; ?>">
			<?php

			foreach ($this->values as $key => $value) {
				$selected = ($opt_val == $key) ? 'selected="selected"' : '';
				echo "<option value='".esc_attr($key)."' ".$selected.">".$value."</option>";
			}
			?>
			</select>
			<?php

		}

		if ($this->description) { ?>
			<p class="description"><?php echo $this->description; ?></p>
			<?php
		}
		?>
		</td></tr>

		<?php
	}


}


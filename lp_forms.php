<?php
/**
 * Implements hook_theme().
 */
function lp_forms_theme()
{
    return array(
    'lp_forms_form' => array(
      'variables' => array('output' => null),
      'template' => 'lpform',
    ),
  );
}

/**
 * Implements hook_menu().
 */
function lp_forms_menu()
{

	/* Display form */
	$items['form-api/show/%'] = array(
    'page callback' => 'lp_forms_show',
    'page arguments' => array(2),
    'access arguments' => array('fill loyal forms'),
    'type' => MENU_NORMAL_ITEM,
  );

	/* Submit customer form */
    $items['lp_forms_forms_handler'] = array(
    'page callback' => 'lp_forms_forms_handler_callback',
	 'access arguments' => array('fill loyal forms'),
    'type' => MENU_CALLBACK,
    'delivery callback' => 'ajax_deliver',
    'theme callback' => 'ajax_base_page_theme',
  );

  /* Download file */
   $items['download/%'] = array(
    'page callback' => 'lp_forms_download_file',
     'page arguments' => array(1),
      'access arguments' => array('fill loyal forms'),
    'type' => MENU_CALLBACK,
  );

	/* Download's report */

    $items['report/downloads/%'] = array(
    'page callback' => 'lp_forms_downloads_report',
    'page arguments' => array(2),
	'access callback' => 'user_is_logged_in',
    'type' => MENU_NORMAL_ITEM,
  );

  /* Load font list */
    $items['fonts-list'] = array(
    'page callback' => 'lp_forms_fonts_list',
    'type' => MENU_NORMAL_ITEM,
    'access callback' => 'user_is_logged_in'
  );



    return $items;
}

/**
  * Process download file by fid.
  *
  * @fid int
  *
  */
function lp_forms_download_file($fid)
{
    $file = file_load($fid);
    if (empty($file)) {
        return drupal_not_found();
    }
    $filepath = drupal_realpath($file->uri);
    $filename = basename($filepath);
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    drupal_add_http_header('Content-disposition', 'attachment; filename=' . $filename);
    readfile($filepath);
}

/**
  * Load fonts list form database (Only for admin)
  *
  */

function lp_forms_fonts_list()
{

	$font_select = array(
		'#type' => 'select',
		'#prefix' => '<label class="control-label" for="select_font">' . t('Form Font') . '</label>',
		'#attributes' => array(
			'class'	  => array('form-control'),
			'onclick' => array('_setFont(this)'),
			'id' => 'select_font'
		),
		'#options' => array('' => t('Select Font'))
	 );

	$result = db_query("SELECT name,metadata FROM {fontyourface_font}");
    foreach ($result as $font) {

		$data = unserialize($font->metadata);

		if ($data['subset'] != 'latin') {
            continue;
        }

        $showname = str_replace('(latin)', '', $font->name);
		$font_select['#options'][$font->name] = $showname;
    }


	 print drupal_render($font_select);

}


/**
  * Page callback for report <Form Title> Entries
  *
  * @nid int Nid of the report's node
  *
  */

function lp_forms_downloads_report($nid)
{
    if (user_is_logged_in()) {
        $node = node_load($nid);
        return '<div class="container"><div class="row"><div class="col-xs-12"><h1 class="page-header">'.$node->title.' Entries</h1><div class="panel panel-default">
      <div class="panel-body">'
    . views_embed_view('form_report', 'block_1', $nid) .
    '</div></div></div></div>';
    } else {
        return drupal_not_found();
    }
}

/**
 * Implements hook_form_validate().
 */
function lp_forms_embedded_form_validate($form, &$form_state)
{
    $email = $form_state['values']['email'];
    if (!valid_email_address($email)) {
        form_set_error('email', t('Please Enter a valid email address.'));
    }
}

/**
 * Implements hook_form().
 */
function lp_forms_embedded_form($form, &$form_state)
{
    $form['#attributes'] = array('class'=>'form-inline');
    $form['messages'] = array(
     '#markup' => '<div id="ajax-forms-messages"></div>',
     '#weight' => -50,
   );
    $form['name']['customer_form_id'] = array(
     '#type' => 'hidden',
     '#title' => t('Customer Form ID'),
     '#required' => true,
     '#attributes' => array('required'=>'required')
   );
    $form['name']['first'] = array(
    '#type' => 'textfield',
    '#title' => t('First Name'),
    '#required' => true,
    '#size' => 100,
    '#maxlength' => 100,
    '#attributes' => array('required'=>'required','size'=>'')
  );
    $form['name']['last'] = array(
    '#type' => 'textfield',
    '#title' => t('Last Name'),
    '#size' => 100,
    '#maxlength' => 100,
    '#attributes' => array('required'=>'required','size'=>'')
  );
    $form['name']['email'] = array(
    '#type' => 'textfield',
    '#title' => t('Email'),
    '#size' => 100,
    '#maxlength' => 100,
    '#required' => true,
    '#attributes' => array('required'=>'required','size'=>'')
  );
    $form['submit'] = array(
    '#type' => 'submit',
    '#value' => 'Submit'
  );
    $form['submit']['#ajax'] = array(
     'path' => 'lp_forms_forms_handler',
   );
    $form['#attached']['js'][] = drupal_get_path('module', 'lp_forms').'/scripts.js';
    $form['#validate'] = array('lp_forms_embedded_form_validate');

    return $form;
}

/**
 * Submit handler for custom form.
 */

function lp_forms_forms_handler_callback()
{
    list($form, $form_state) = ajax_get_form();
    drupal_process_form($form['#form_id'], $form, $form_state);
    lp_forms_embedded_form_validate($form, $form_state);


  //insert into db emails
  if (!form_get_errors()) {

      $form = node_load($form_state['values']['customer_form_id']);

      if (!empty($form) && $form->type == 'forms') {
          $node = new stdClass();
          $node->type = "customer";
          node_object_prepare($node);
          $node->field_first_name[LANGUAGE_NONE][0]['value'] = $form_state['values']['first'];
          $node->field_customer_email[LANGUAGE_NONE][0]['email'] = $form_state['values']['email'];
          $node->field_date_of_visit[LANGUAGE_NONE][0]['value'] = date('Y-m-d H:i:s');
          $node->field_form_id[LANGUAGE_NONE][0]['value'] = $form_state['values']['customer_form_id'];
          $node->title = $form_state['values']['last'];
          $node->uid = $form->uid;

          $node->language = LANGUAGE_NONE;
          $node->status = 1;
          $node->promote = 0;
          $node->comment = 0;
          $node = node_submit($node);
          node_save($node);


          $url = file_create_url($form->field_downloadable_content_pdf[LANGUAGE_NONE][0]['uri']);
          drupal_set_message($form->field_thank_you_message[LANGUAGE_NONE][0]['value']);
          drupal_set_message('If download hasn"t started, please <a target="_blank" class="dlink" href="/download/' . $form->field_downloadable_content_pdf[LANGUAGE_NONE][0]['fid'] . '">click here</a>.');

      } else {

          drupal_set_message(t('Access denied.'), 'error');

      }
  }

    $commands = array();

	//clear stack
	drupal_get_messages('error');

    if (!form_get_errors()) {



		$messages = array('#markup' =>  theme('status_messages') . '<iframe style="display:none;" src="/download/' . $form->field_downloadable_content_pdf[LANGUAGE_NONE][0]['fid'] .'"></iframe>' );

        $commands[] = ajax_command_html('#ajax-forms-messages', drupal_render($messages));
        $commands[] = ajax_command_remove('.form-item');
        $commands[] = ajax_command_remove('#edit-submit');
    } else {
        $errors = array();
        foreach (form_get_errors() as $key=>$value) {
            $errors[] = array('key'=>$key,'value'=>$value);
        }

        $commands[] = ajax_command_html('#ajax-forms-messages', '<script>_validateFormFields(' . json_encode($errors) . ');</script>');
    }
    return array('#type' => 'ajax', '#commands' => $commands);
}

/**
 * Page callback for showing custom form
 */

function lp_forms_show($nid)
{
    $node = node_load(intval($nid));
    if (!empty($node) && $node->type == 'forms') {
        $title = '';
        $title_color = '';
        if (!empty($node->field_form_title[LANGUAGE_NONE][0]['value'])) {
            $title = $node->field_form_title[LANGUAGE_NONE][0]['value'];
        }
        if (!empty($node->field_form_title_color[LANGUAGE_NONE][0]['jquery_colorpicker'])) {
            $title_color = '#' . $node->field_form_title_color[LANGUAGE_NONE][0]['jquery_colorpicker'];
        }

        $button_text = $node->field_button_text[LANGUAGE_NONE][0]['value'];
        $button_color = $node->field_button_color[LANGUAGE_NONE][0]['jquery_colorpicker'];
        $button_text_color = $node->field_button_text_color[LANGUAGE_NONE][0]['jquery_colorpicker'];
        $form_font = $node->field_form_font[LANGUAGE_NONE][0]['value'];


        $form_array = drupal_get_form('lp_forms_embedded_form');

        if (!empty($button_text)) {
            $form_array['submit']['#value'] = '&nbsp;' . $button_text . '&nbsp;';
        }

        $buttons_styles = '';

        if (!empty($button_color)) {
            $buttons_styles = 'background-color:#' . $button_color . ';';
        }
        if (!empty($button_text_color)) {
            $buttons_styles .= 'color:#' . $button_text_color . ';';
        }

        $custom_css = $node->field_custom_css[LANGUAGE_NONE][0]['value'];

        $form_array['submit']['#attributes'] = array('style'=>$buttons_styles);
        $form_array['name']['customer_form_id']['#value'] = $node->nid;

        $form =  drupal_render($form_array);

        $font_link = '';
        $font_css = '';

        if (!empty($form_font)) {
            $node = db_query("SELECT * FROM {fontyourface_font} WHERE name = :name", array(':name' => $form_font))->fetchObject();
            if ($node) {
                $params = unserialize($node->metadata);
                $font_link = '<link href="https://fonts.googleapis.com/css?family='.$params['path'].'&amp;subset='.$params['subset'].'" rel="stylesheet"> ';
                $font_css = "input,label,h5,div{font-family: '" . $node->css_family . "', sans-serif !important;}";
            }
        }

		$output = array('#markup' => theme('lp_forms_form', array(
         'output' => $form,
         'title'=>$title,
         'title_color'=>$title_color,
         'custom_css'=>$custom_css,
         'font_link'=> $font_link,
         'font_css'=> $font_css
     )));

        return drupal_render($output);

    } else {
        return drupal_not_found();
    }
}
/**
  * Return form code to customer
  *
  * @nid int string Nid ofthe form's node
  *
  * @return string
  */
function lp_forms_get_code($nid)
{
    global $base_url ;
    $formcode = htmlspecialchars('<iframe style="width:370px;height:280px;border:none;" src="' . $base_url  . '/form-api/show/' . $nid . '"></iframe>');
    drupal_add_js(drupal_get_path('module', 'lp_forms') . '/scripts.js');
    return '<div class="panel panel-default"><div class="panel-heading"><h4 class="panel-title">Form Code&nbsp;&nbsp;</h4></div><div class="panel-body"><div id="fcode">'.  $formcode .'</div><div id="pblock" style="display:none;"></div></div><div class="panel-footer"><a href="javascript:void(0);" onclick="showPreview()" >Preview</a> (Save Form to Update Preview)</div></div>';
}

/**
 * Implements hook_form_alter().
 */
function lp_forms_form_alter(&$form, &$form_state, $form_id)
{
	if ($form_id == 'forms_node_form') {

        if (!empty($form_state['node'])) {
            $form['#prefix'] = lp_forms_get_code($form_state['node']->nid);
        }

        $form['field_form_font']['#suffix'] = '<div id="font_area"></div>';

        drupal_add_js(drupal_get_path('module', 'lp_forms') . '/fonts.js');

	}

    if ($form_id == 'customer_node_form') {
        if (isset($form['field_form_id']) && !user_access('administer')) {
            $form['field_form_id']['#access'] = false;
        }
    }
}

/**
 * Implements hook_permission().
 */
function lp_forms_permission() {
  return array(
    'fill loyal forms' => array(
      'title' => t('Fill Custom Forms'),
      'description' => t('Allows users to fill out custom forms.'),
    ),
  );
}

/**
 * Implements hook_page_alter().
 */
function lp_forms_page_alter(&$page)
{
  if(arg(0) == 'form-api')
  {
    header_remove('X-Frame-Options');
  }

}

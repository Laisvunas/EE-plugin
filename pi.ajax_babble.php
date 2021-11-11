<?php

/*
=====================================================
 This ExpressionEngine plugin was created by Laisvunas
=====================================================
 Copyright (c) Laisvunas All rights reserved
=====================================================
 Purpose: Use AJAX to submit comments 
=====================================================
*/

$plugin_info = array(
						'pi_name'			=> 'AJAX Babble',
						'pi_version'		=> '2.3.6',
						'pi_author'			=> 'Laisvunas',
						'pi_author_url'		=> '',
						'pi_description'	=> 'Uses AJAX to submit comments.',
						'pi_usage'			=> Ajax_babble::usage()
					);

class Ajax_babble {
  
  var $spam_indicator_phrase = 'spam';
  var $moderation_indicator_phrase = 'moderat';
  
 	function comments()
 	{
 		$this->EE =& get_instance();
 
 		$tagdata = $this->EE->TMPL->tagdata;
 		
   $entry_id = $this->EE->TMPL->fetch_param('entry_id');
   $limit = $this->EE->TMPL->fetch_param('limit') ? $this->EE->TMPL->fetch_param('limit'): 100;
   $max_pagination_links = $this->EE->TMPL->fetch_param('max_pagination_links') ? $this->EE->TMPL->fetch_param('max_pagination_links') : 2;
   $search_trigger = $this->EE->TMPL->fetch_param('search_trigger');
   $sort = $this->EE->TMPL->fetch_param('sort') ? $this->EE->TMPL->fetch_param('sort') : 'desc';
		 $orderby = $this->EE->TMPL->fetch_param('orderby') ? $this->EE->TMPL->fetch_param('orderby') : 'date';
   $status = $this->EE->TMPL->fetch_param('status');
   $css_id_to_scroll = $this->EE->TMPL->fetch_param('css_id_to_scroll') ? $this->EE->TMPL->fetch_param('css_id_to_scroll') : '';// used to scroll into view
   $parse_pagination_links = ($this->EE->TMPL->fetch_param('parse_pagination_links') == 'no') ? FALSE : TRUE;

   $pagination_segment = '';
   $javascript = '';
   $comment_submitted = $this->EE->input->post('comment_submitted');
   $onload_event_start = '';
   $onload_event_end = '';
   
   // Case 1: comments template is being loaded because its parent template is being loaded
   if ($this->EE->TMPL->fetch_param('entry_id') AND !$this->EE->input->post('pagination_number'))
   {
     $main_template_loaded = 'yes';
     
     // page load event code
     $onload_event_start = "ajaxBabbleEntry".$entry_id.".addEvent(window, 'load', function() {";
     $onload_event_end = "});";
     
     $tagdata = str_replace(LD.'ajax_babble_entry_id'.RD, $entry_id, $tagdata);

     $comments_number = $this->_comments_number($entry_id, $status);
     $pagination_number = 0;
     
     $pagination_links = $this->_pagination_links($comments_number, $limit, $pagination_number, $max_pagination_links, $entry_id);
     
     if ($parse_pagination_links)
     {
       $tagdata = str_replace(LD.'pagination_links'.RD, $pagination_links, $tagdata);
     }
     
     $sql_entry = "SELECT exp_channel_titles.url_title, exp_channels.channel_name 
                   FROM 
                     exp_channel_titles
                       INNER JOIN
                     exp_channels
                       ON
                     exp_channel_titles.channel_id = exp_channels.channel_id
                   WHERE exp_channel_titles.entry_id='".$entry_id."'
                   LIMIT 1";
     $query_entry = $this->EE->db->query($sql_entry);
     if ($query_entry->num_rows() == 1)
     {
       $url_title = $query_entry->row('url_title');
       $weblog_name = $query_entry->row('channel_name');
     }
     else
     {
       $url_title = '';
       $weblog_name = '';
     }
     $tagdata = str_replace(LD.'ajax_babble_url_title'.RD, $url_title, $tagdata);
     $tagdata = str_replace(LD.'ajax_babble_weblog_name'.RD, $weblog_name, $tagdata);
     $tagdata = str_replace(LD.'ajax_babble_channel_name'.RD, $weblog_name, $tagdata);
     
     $tagdata = str_replace(LD.'ajax_babble_css_id_to_scroll'.RD, $css_id_to_scroll, $tagdata);
     
     $ajax_babble_search_trigger_segment = '';
     for ($i = 1; $i <= 9; $i++)
     {
       $var_name = 'segment_'.$i;
       if (@$this->EE->uri->segments[$i])
       {
         if ($search_trigger AND strpos($this->EE->uri->segments[$i], $search_trigger) === 0)
         {
           $ajax_babble_search_trigger_segment = $this->EE->uri->segments[$i];
         }
         $$var_name = $this->EE->uri->segments[$i];
       }
       else
       {
         $$var_name = '';
       }
       $tagdata = str_replace(LD.'ajax_babble_segment_'.$i.RD, $$var_name, $tagdata);
     }
     $tagdata = str_replace(LD.'ajax_babble_search_trigger_segment'.RD, $ajax_babble_search_trigger_segment, $tagdata);
 
     
     if ($search_trigger)
     {
       $certain_comment_id = $this->_find_comment_id_in_url($search_trigger);
       if ($certain_comment_id)
       {

         $non_ajax_pagination_number = $this->_find_comment($certain_comment_id, $entry_id, $orderby, $sort, $limit);

         if ($non_ajax_pagination_number > 0) // Comments will be loaded via ajax
         {
           $tagdata = '';
         }
         else // Needed comment is on the first paginated page
         { 
           $tagdata = str_replace(LD.'ajax_babble_comment_id_to_scroll'.RD, $certain_comment_id, $tagdata);
         }
       }
       else
       {

         $tagdata = str_replace(LD.'ajax_babble_comment_id_to_scroll'.RD, '', $tagdata);

       }
     }
   }
   // Case 2: comments template is being loaded because pagination link was clicked or certain comment was found on the first paginated page
   elseif (is_numeric($this->EE->input->post('pagination_number')))
   {     

     $entry_id = $this->EE->uri->segments[3];
     $tagdata = str_replace(LD.'ajax_babble_entry_id'.RD, $entry_id, $tagdata);

     if (is_numeric($this->EE->input->post('certain_comment_id')))
     {
       $certain_comment_id = $this->EE->input->post('certain_comment_id');
       $tagdata = str_replace(LD.'ajax_babble_comment_id_to_scroll'.RD, $certain_comment_id, $tagdata);
     }
     else
     {
       $tagdata = str_replace(LD.'ajax_babble_comment_id_to_scroll'.RD, '', $tagdata);
     }
     
     // variable {pagination_links}
     $comments_number = $this->_comments_number($entry_id, $status);
     $pagination_number = $this->EE->input->post('pagination_number');
     
     $pagination_links = $this->_pagination_links($comments_number, $limit, $pagination_number, $max_pagination_links, $entry_id);
     
     if ($parse_pagination_links)
     {
       $tagdata = str_replace(LD.'pagination_links'.RD, $pagination_links, $tagdata);
     }
     

     if ($this->EE->input->post('url_title'))
     {
       $tagdata = str_replace(LD.'ajax_babble_url_title'.RD, $this->EE->input->post('url_title'), $tagdata);
     }
     else
     {
       $tagdata = str_replace(LD.'ajax_babble_url_title'.RD, '', $tagdata);
     }
     

     $sql_entry = "SELECT exp_channels.channel_name 
                   FROM 
                     exp_channel_titles
                       INNER JOIN
                     exp_channels
                       ON
                     exp_channel_titles.channel_id = exp_channels.channel_id
                   WHERE exp_channel_titles.entry_id='".$entry_id."'
                   LIMIT 1";
     $query_entry = $this->EE->db->query($sql_entry);
     if ($query_entry->num_rows() == 1)
     {
       $weblog_name = $query_entry->row('channel_name');
     }
     else
     {
       $weblog_name = '';
     }
     $tagdata = str_replace(LD.'ajax_babble_weblog_name'.RD, $weblog_name, $tagdata);
     $tagdata = str_replace(LD.'ajax_babble_channel_name'.RD, $weblog_name, $tagdata);
     

     if ($_POST["css_id_to_scroll"])
     {
       $tagdata = str_replace(LD.'ajax_babble_css_id_to_scroll'.RD, $_POST["css_id_to_scroll"], $tagdata);
     }
     else
     {
       $tagdata = str_replace(LD.'ajax_babble_css_id_to_scroll'.RD, '', $tagdata);
     }
     

     $ajax_babble_search_trigger_segment = '';
     for ($i = 1; $i <= 9; $i++)
     {
       $var_name = 'segment_'.$i;
       if ($this->EE->input->post($var_name))
       {
         if ($search_trigger AND strpos($this->EE->input->post($var_name), $search_trigger) === 0)
         {
           $ajax_babble_search_trigger_segment = $this->EE->input->post($var_name);
         }
         $tagdata = str_replace(LD.'ajax_babble_segment_'.$i.RD, $this->EE->input->post($var_name), $tagdata);
       }
       else
       {
         $tagdata = str_replace(LD.'ajax_babble_segment_'.$i.RD, '', $tagdata);
       }
     }
     $tagdata = str_replace(LD.'ajax_babble_search_trigger_segment'.RD, $ajax_babble_search_trigger_segment, $tagdata);
   }
   // Case 3: comments template is being loaded because new comment was submitted
   elseif ($comment_submitted)
   {         
     
     $this->EE->load->add_package_path(PATH_MOD.'comment/');
     $this->EE->load->model('comment_model');
     if ( ! class_exists('Comment'))
     {
     	require PATH_MOD.'comment/'.'mod.comment.php';
     }
     $COMM = new Comment();

     $COMM->insert_new_comment();
   }
   // Case 4: comments template is being loaded because after comment submission Comment module performed redirect
   else
   {
     $comment_submitted = 'yes';
     
     // variable {ajax_babble_entry_id}
     $entry_id = $this->EE->uri->segments[3];
     $tagdata = str_replace(LD.'ajax_babble_entry_id'.RD, $entry_id, $tagdata);

     $comments_number = $this->_comments_number($entry_id, $status);
     
     $pagination_number = 0;
     $segment_6 = $this->EE->uri->segments[6];
     if (is_numeric(substr($segment_6, 1)))
     {
       $pagination_number = substr($segment_6, 1);
     }
     
     $pagination_links = $this->_pagination_links($comments_number, $limit, $pagination_number, $max_pagination_links, $entry_id);
     
     if ($parse_pagination_links)
     {
       $tagdata = str_replace(LD.'pagination_links'.RD, $pagination_links, $tagdata);
     }
     
     $sql_entry = "SELECT exp_channel_titles.url_title, exp_channels.channel_name 
                   FROM 
                     exp_channel_titles
                       INNER JOIN
                     exp_channels
                       ON
                     exp_channel_titles.channel_id = exp_channels.channel_id
                   WHERE exp_channel_titles.entry_id='".$entry_id."'
                   LIMIT 1";
     $query_entry = $this->EE->db->query($sql_entry);
     if ($query_entry->num_rows() == 1)
     {
       $url_title = $query_entry->row('url_title');
       $weblog_name = $query_entry->row('channel_name');
     }
     else
     {
       $url_title = '';
       $weblog_name = '';
     }
     $tagdata = str_replace(LD.'ajax_babble_url_title'.RD, $url_title, $tagdata);
     $tagdata = str_replace(LD.'ajax_babble_weblog_name'.RD, $weblog_name, $tagdata);
     $tagdata = str_replace(LD.'ajax_babble_channel_name'.RD, $weblog_name, $tagdata);
     
     if ($this->EE->uri->segments[5] == 'none')
     {
       $tagdata = str_replace(LD.'ajax_babble_css_id_to_scroll'.RD, '', $tagdata);
     }
     else
     {
       $tagdata = str_replace(LD.'ajax_babble_css_id_to_scroll'.RD, $this->EE->uri->segments[5], $tagdata);
     }
     
     $segments_string = $this->EE->uri->segments[4];
     if ($segments_string == 'no_url_segments')
     {
       $segments_string = '';
     }
     $segments_array = explode('--__--', $segments_string);
     
     $ajax_babble_search_trigger_segment = '';
     for ($i = 1; $i <= 9; $i++)
     {
       if (isset($segments_array[$i - 1]) AND $segments_array[$i - 1])
       {
         if ($search_trigger AND strpos($segments_array[$i - 1], $search_trigger) === 0)
         {
           $ajax_babble_search_trigger_segment = $segments_array[$i - 1];
         }
         $tagdata = str_replace(LD.'ajax_babble_segment_'.$i.RD, $segments_array[$i - 1], $tagdata);
       }
       else
       {
         $tagdata = str_replace(LD.'ajax_babble_segment_'.$i.RD, '', $tagdata);
       }
     }
     $tagdata = str_replace(LD.'ajax_babble_search_trigger_segment'.RD, $ajax_babble_search_trigger_segment, $tagdata);
     
     $sql_last_comment_id = "SELECT comment_id
                             FROM exp_comments
                             WHERE entry_id='".$entry_id."'
                             ORDER BY comment_id DESC
                             LIMIT 1";
     $query_last_comment_id = $this->EE->db->query($sql_last_comment_id);
     if ($query_last_comment_id->num_rows() == 1)
     {
       $last_comment_id = $query_last_comment_id->row('comment_id');
       $tagdata = str_replace(LD.'ajax_babble_comment_id_to_scroll'.RD, $last_comment_id, $tagdata);
     }
     else
     {
       $tagdata = str_replace(LD.'ajax_babble_comment_id_to_scroll'.RD, '', $tagdata);
     }
   }
   
   $xid_hash = $this->EE->functions->add_form_security_hash('{XID_HASH}');
   
   // javascript
   ob_start();
?>

<script type="text/javascript">
//<![CDATA[

//=================================================
//
// script ajaxBabbleEmbedEntry<?= $entry_id ?>
//
//=================================================

ajaxBabbleEmbedEntry<?= $entry_id ?> = {

  comments_number: '<?= $comments_number ?>',
  
  limit: '<?= $limit ?>',
  
  sort: '<?= @$sort ?>',
  
  comment_submitted: '<?= @$comment_submitted ?>',
  
  main_template_loaded: '<?= @$main_template_loaded ?>',
  
  xid_hash: '<?= @$xid_hash ?>',
  
  search_trigger: '<?= @$search_trigger ?>',
  
  non_ajax_pagination_number: '<?= @$non_ajax_pagination_number ?>',
  
  certain_comment_id: '<?= @$certain_comment_id ?>',
  
  insert_comments_number: function() {
    var comment_num_ids;
    var comment_num_el;
    var previous_num;
    
    if (ajaxBabbleEmbedEntry<?= $entry_id ?>.comment_submitted) {
      comment_num_ids = [];
      if (ajaxBabbleEntry<?= $entry_id ?>.comments_number_id != '') {
        comment_num_ids = ajaxBabbleEntry<?= $entry_id ?>.comments_number_id.split('|');
        for (var i = 0; i < comment_num_ids.length; i++) {
          comment_num_el = document.getElementById(comment_num_ids[i]);
          if (comment_num_el) {
            previous_num = parseInt(comment_num_el.innerHTML);
            if (isNaN(previous_num)) {
              comment_num_el.innerHTML = ajaxBabbleEmbedEntry<?= $entry_id ?>.comments_number;
            }
            else {
              comment_num_el.innerHTML = previous_num + 1;
            }
          }
        }
      }
    }
  },
  
  change_xid_hash: function() {
    var comment_form;
    
      comment_form = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.form_id);
      for (var i = 0; i < comment_form.elements.length; i++) {
        if (comment_form.elements[i].name == 'XID' || comment_form.elements[i].name == 'csrf_token') {
          comment_form.elements[i].value = ajaxBabbleEmbedEntry<?= $entry_id ?>.xid_hash;
          break;
        } 
      }

  },
  
  ajaxLoadCertainComment: function() {
    if (ajaxBabbleEmbedEntry<?= $entry_id ?>.search_trigger && ajaxBabbleEmbedEntry<?= $entry_id ?>.non_ajax_pagination_number > 0) {
      ajaxBabbleEntry<?= $entry_id ?>.fetchComments(ajaxBabbleEmbedEntry<?= $entry_id ?>.non_ajax_pagination_number, ajaxBabbleEmbedEntry<?= $entry_id ?>.certain_comment_id, true);
    }
  }
  

}

<?= $onload_event_start ?>
ajaxBabbleEmbedEntry<?= $entry_id ?>.ajaxLoadCertainComment();
ajaxBabbleEntry<?= $entry_id ?>.scrollCommentIntoView();
ajaxBabbleEmbedEntry<?= $entry_id ?>.insert_comments_number();
ajaxBabbleEmbedEntry<?= $entry_id ?>.change_xid_hash();
<?= $onload_event_end ?>

//]]>
</script>

<?php
   $javascript = ob_get_contents();
 		ob_end_clean();
   
   return $tagdata.$javascript;
	}
 
 function script()
 {   
   $this->EE =& get_instance();
   
   // fetch parameters
   $form_id = $this->EE->TMPL->fetch_param('form_id');
   $entry_id = $this->EE->TMPL->fetch_param('entry_id');
   $comments_template_url = $this->EE->TMPL->fetch_param('comments_template_url');
   $comments_container_id = $this->EE->TMPL->fetch_param('comments_container_id');
   $submit_button_id = $this->EE->TMPL->fetch_param('submit_button_id');
   $comments_number_id = $this->EE->TMPL->fetch_param('comments_number_id');
   $css_id_to_scroll = $this->EE->TMPL->fetch_param('css_id_to_scroll'); // used to scroll into view
   $preview_template_url = $this->EE->TMPL->fetch_param('preview_template_url');
   $preview_container_id = $this->EE->TMPL->fetch_param('preview_container_id');
   $preview_button_id = $this->EE->TMPL->fetch_param('preview_button_id');
   $comments_progress_indicator_id = $this->EE->TMPL->fetch_param('comments_progress_indicator_id');
   $preview_progress_indicator_id = $this->EE->TMPL->fetch_param('preview_progress_indicator_id');
   $progress_indicator_class = $this->EE->TMPL->fetch_param('progress_indicator_class');
   $comments_error_message_id = $this->EE->TMPL->fetch_param('comments_error_message_id');
   $preview_error_message_id = $this->EE->TMPL->fetch_param('preview_error_message_id');
   $error_message_class = $this->EE->TMPL->fetch_param('error_message_class');
   $add_callback_submit = $this->EE->TMPL->fetch_param('add_callback_submit') ? $this->EE->TMPL->fetch_param('add_callback_submit') : 'null';
   $add_callback_submit_args = $this->EE->TMPL->fetch_param('add_callback_submit_args');
   $add_callback_paginate = $this->EE->TMPL->fetch_param('add_callback_paginate') ? $this->EE->TMPL->fetch_param('add_callback_paginate') : 'null';
   $add_callback_paginate_args = $this->EE->TMPL->fetch_param('add_callback_paginate_args');
   $add_callback_delete = $this->EE->TMPL->fetch_param('add_callback_delete') ? $this->EE->TMPL->fetch_param('add_callback_delete') : 'null';
   $add_callback_delete_args = $this->EE->TMPL->fetch_param('add_callback_delete_args');
   $empty_comment_message_text = $this->EE->TMPL->fetch_param('empty_comment_message_text');
   $empty_comment_display_js_alert = ($this->EE->TMPL->fetch_param('empty_comment_display_js_alert') == 'yes') ? 'true' : 'false';
   $pagination_symbol = $this->EE->TMPL->fetch_param('pagination_symbol') ? $this->EE->TMPL->fetch_param('pagination_symbol') : 'N';
   $protect_backslashes = ($this->EE->TMPL->fetch_param('protect_backslashes') == 'yes') ? TRUE : FALSE;
   
   // some parameters are required
   if (!$form_id)
   {
     echo 'ERORR! "form_id" parameter of ext:ajax_babble:script tag must be defined!'.'<br><br>'.PHP_EOL;
   }
   if (!$entry_id)
   {
     echo 'ERORR! "entry_id" parameter of ext:ajax_babble:script tag must be defined!'.'<br><br>'.PHP_EOL;
   }
   if (!$comments_container_id)
   {
     echo 'ERORR! "comments_container_id" parameter of ext:ajax_babble:script tag must be defined!'.'<br><br>'.PHP_EOL;
   }
   if (!$comments_template_url)
   {
     echo 'ERORR! "comments_template_url" parameter of ext:ajax_babble:script tag must be defined!'.'<br><br>'.PHP_EOL;
   }
   if (!$submit_button_id)
   {
     echo 'ERORR! "submit_button_id" parameter of ext:ajax_babble:script tag must be defined!'.'<br><br>'.PHP_EOL;
   }
   
   $comments_template_url = $this->_template_url($comments_template_url);
   
   if ($add_callback_submit != 'null')
   {
     $add_callback_submit_array = '['.str_replace('|', ',', $add_callback_submit).']';
     $submit_callbacks = explode('|', $add_callback_submit);
     if (!$add_callback_submit_args)
     {
       foreach ($submit_callbacks as $function_name)
       {
         if ($this->EE->TMPL->fetch_param($function_name))
         {
           $add_callback_submit_args .= $this->EE->TMPL->fetch_param($function_name).',';
         }
         else
         {
           $add_callback_submit_args .= 'null,';
         }
       }
       $add_callback_submit_args = '['.rtrim($add_callback_submit_args, ',').']';
     }
     else
     {
       $add_callback_submit_args = '['.str_replace('|', ',', $add_callback_submit_args).']';
     }
   }
   else
   {
     $add_callback_submit_array = 'null';
     $add_callback_submit_args = 'null';
   }
   
   if ($add_callback_paginate != 'null')
   {
     $add_callback_paginate_array = '['.str_replace('|', ',', $add_callback_paginate).']';
     $paginate_callbacks = explode('|', $add_callback_paginate);
     if (!$add_callback_paginate_args)
     {
       foreach ($paginate_callbacks as $function_name)
       {
         if ($this->EE->TMPL->fetch_param($function_name))
         {
           $add_callback_paginate_args .= $this->EE->TMPL->fetch_param($function_name).',';
         }
         else
         {
           $add_callback_paginate_args .= 'null,';
         }
       }
       $add_callback_paginate_args = '['.rtrim($add_callback_paginate_args, ',').']';
     }
     else {
       $add_callback_paginate_args = '['.str_replace('|', ',', $add_callback_paginate_args).']';
     }
   }
   else
   {
     $add_callback_paginate_array = 'null';
     $add_callback_paginate_args = 'null';
   }
   
   if ($add_callback_delete != 'null')
   {
     $add_callback_delete_array = '['.str_replace('|', ',', $add_callback_delete).']';
     $delete_callbacks = explode('|', $add_callback_delete);
     if (!$add_callback_delete_args)
     {
       foreach ($delete_callbacks as $function_name)
       {
         if ($this->EE->TMPL->fetch_param($function_name))
         {
           $add_callback_delete_args .= $this->EE->TMPL->fetch_param($function_name).',';
         }
         else
         {
           $add_callback_delete_args .= 'null,';
         }
       }
       $add_callback_delete_args = '['.rtrim($add_callback_delete_args, ',').']';
     }
     else
     {
       $add_callback_delete_args = '['.str_replace('|', ',', $add_callback_delete_args).']';
     }
   }
   else
   {
     $add_callback_delete_array = 'null';
     $add_callback_delete_args = 'null';
   }
   
   $sql_entry = "SELECT exp_channel_titles.url_title, exp_channels.comment_moderate 
                 FROM exp_channel_titles, exp_channels
                 WHERE exp_channel_titles.entry_id='".$entry_id."' AND exp_channels.channel_id = exp_channel_titles.channel_id
                 LIMIT 1";
   $query_entry = $this->EE->db->query($sql_entry);
   if ($query_entry->num_rows() == 1)
   {
     $url_title = $query_entry->row('url_title');
     $comment_moderate = $query_entry->row('comment_moderate');
     if ($comment_moderate == 'y')
     {
       $this->EE->lang->loadfile('comment');
       $comment_moderate_alert_text = $this->EE->lang->line('cmt_comment_accepted').'. '.$this->EE->lang->line('cmt_will_be_reviewed');
     }
   }
   
   for ($i = 1; $i <= 9; $i++)
   {
     $var_name = 'segment_'.$i;
     if (@$this->EE->uri->segments[$i])
     {
       $$var_name = $this->EE->uri->segments[$i];
     }
     else
     {
       $$var_name = '';
     }
   }
   
   // javascript
   ob_start();
?>

<script type="text/javascript">
//<![CDATA[

//=================================================
//
// script ajaxBabbleEntry<?= $entry_id ?>
//
//=================================================

// Create form init object
if (form_<?= @$form_id ?>_init == undefined) {
  var form_<?= @$form_id ?>_init = {};
  form_<?= @$form_id ?>_init.functions = [];
  form_<?= @$form_id ?>_init.done = false;
}

// Create form button object
if (form_<?= @$form_id ?>_button == undefined) {
  var form_<?= @$form_id ?>_button = {};
  form_<?= @$form_id ?>_button.submit_button_id = '<?= @$submit_button_id ?>';
  form_<?= @$form_id ?>_button.submit_button = '';
  form_<?= @$form_id ?>_button.submit_button_old = '';
  form_<?= @$form_id ?>_button.submit_button_clone = '';
  form_<?= @$form_id ?>_button.submit_button_old_funcs = [];
  form_<?= @$form_id ?>_button.submit_button_clone_funcs = [];
  form_<?= @$form_id ?>_button.submit_button_old_funcs_deferred = [];
  form_<?= @$form_id ?>_button.submit_button_clone_funcs_deferred = [];
  form_<?= @$form_id ?>_button.submit_button_old_funcs_attached = false;
  form_<?= @$form_id ?>_button.submit_button_clone_funcs_attached = false;
}

var ajaxBabbleEntry<?= $entry_id ?> = {

  entry_id: '<?= $entry_id ?>',
  
  comments_template_url: '<?= $comments_template_url ?>',
  
  comments_container_id: '<?= $comments_container_id ?>',
  
  form_id: '<?= $form_id ?>',
  
  comments_number_id: '<?= @$comments_number_id ?>',
  
  css_id_to_scroll: '<?= @$css_id_to_scroll ?>',
  
  preview_button_id: '<?= @$preview_button_id ?>',
  
  preview_template_url: '<?= @$preview_template_url ?>',
  
  preview_container_id: '<?= @$preview_container_id ?>',
  
  comments_progress_indicator_id: '<?= @$comments_progress_indicator_id ?>',
  
  preview_progress_indicator_id: '<?= @$preview_progress_indicator_id ?>',
  
  progress_indicator_class: '<?= @$progress_indicator_class ?>',
  
  comments_error_message_id: '<?= @$comments_error_message_id ?>',
  
  preview_error_message_id: '<?= @$preview_error_message_id ?>',
  
  error_message_class: '<?= @$error_message_class ?>',
  
  add_callback_submit: <?= @$add_callback_submit_array ?>,
  
  add_callback_paginate: <?= @$add_callback_paginate_array ?>,
  
  add_callback_delete: <?= @$add_callback_delete_array ?>,
  
  add_callback_submit_args: <?= @$add_callback_submit_args ?>,
  
  add_callback_paginate_args: <?= @$add_callback_paginate_args ?>,
  
  add_callback_delete_args: <?= @$add_callback_delete_args ?>,
  
  empty_comment_message_text: '<?= @$empty_comment_message_text ?>',
  
  empty_comment_display_js_alert: <?= @$empty_comment_display_js_alert ?>,
  
  pagination_symbol: '<?= @$pagination_symbol ?>',
  
  comment_moderate: '<?= @$comment_moderate ?>',
  comment_moderate_alert_text: '<?= @$comment_moderate_alert_text ?>',
  
  url_title: '<?= @$url_title ?>',
  
  segment_1: '<?= @$segment_1 ?>',
  
  segment_2: '<?= @$segment_2 ?>',
  
  segment_3: '<?= @$segment_3 ?>',
  
  segment_4: '<?= @$segment_4 ?>',
  
  segment_5: '<?= @$segment_5 ?>',
  
  segment_6: '<?= @$segment_6 ?>',
  
  segment_7: '<?= @$segment_7 ?>',
  
  segment_8: '<?= @$segment_8 ?>',
  
  segment_9: '<?= @$segment_9 ?>',
  
  XHR: null,
  
  addEvent: function(elm, evType, fn, useCapture) { //cross-browser event handling by Scott Andrew
   	if(elm.addEventListener){
    		elm.addEventListener(evType, fn, useCapture);
    		return true;
    }
    else if(elm.attachEvent){
    		var r = elm.attachEvent("on" + evType, fn);
    		return r;
    }
    else {
    		elm["on" + evType] = fn;
    }
  },
  
  stopEvent: function(e) {
    if(!e) var e = window.event;
   	e.cancelBubble = true;
   	e.returnValue = false;
   	if (e.stopPropagation) {
    		e.stopPropagation();
    		e.preventDefault();
   	}
   	return false;
  },
  
  addClassName: function(objElement, strClass, blnMayAlreadyExist) {
    if ( objElement.className ) {
     var arrList = objElement.className.split(' ');
     if ( blnMayAlreadyExist ) {
        var strClassUpper = strClass.toUpperCase();
        for ( var i = 0; i < arrList.length; i++ ) {
           if ( arrList[i].toUpperCase() == strClassUpper ) {
              arrList.splice(i, 1);
              i--;
              }
           }
        }
     arrList[arrList.length] = strClass;
     objElement.className = arrList.join(' ');
     }
     else {     
     objElement.className = strClass;
     }
  },
  
  removeClassName: function(objElement, strClass) {
    if ( objElement.className ) {
      var arrList = objElement.className.split(' ');
      var strClassUpper = strClass.toUpperCase();
      for ( var i = 0; i < arrList.length; i++ ) {
        if ( arrList[i].toUpperCase() == strClassUpper ) {
          arrList.splice(i, 1);
          i--;
        }
      }
      objElement.className = arrList.join(' ');

    }

  },
  
  trim: function(str, charlist) {
    var whitespace, l = 0, i = 0;
    str += "";
    
    if (!charlist) {
        whitespace = " \n\r\t\f\x0b\xa0\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u200b\u2028\u2029\u3000";
    } else {
        charlist += "";
        whitespace = charlist.replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "\$1");
    }
    
    l = str.length;
    for (i = 0; i < l; i++) {
        if (whitespace.indexOf(str.charAt(i)) === -1) {
            str = str.substring(i);
            break;
        }
    }
    
    l = str.length;
    for (i = l - 1; i >= 0; i--) {
        if (whitespace.indexOf(str.charAt(i)) === -1) {
            str = str.substring(0, i + 1);
            break;
        }
    }
    
    return whitespace.indexOf(str.charAt(0)) === -1 ? str : "";
  },
  
  createXHRObject: function() {
    try {
   		 ajaxBabbleEntry<?= $entry_id ?>.XHR = new ActiveXObject("Msxml2.XMLHTTP");
   	}
    catch (e) {
      try {
        ajaxBabbleEntry<?= $entry_id ?>.XHR = new ActiveXObject("Microsoft.XMLHTTP");
      }
      catch (e2) {
        ajaxBabbleEntry<?= $entry_id ?>.XHR = null;
      }
    }
    if(!ajaxBabbleEntry<?= $entry_id ?>.XHR && typeof XMLHttpRequest != "undefined") {
      ajaxBabbleEntry<?= $entry_id ?>.XHR = new XMLHttpRequest();
    }
    if (!ajaxBabbleEntry<?= $entry_id ?>.XHR) {
      alert('This browser does not support AJAX!'); 
      return false;
    }
    else {
      return ajaxBabbleEntry<?= $entry_id ?>.XHR;
    }
  },
  
  fetchComments: function(pagination_number, certain_comment_id, progress_idicator_needed) {
    var final_url;
    var data;
    
    ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.preview_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
    ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
    ajaxBabbleEntry<?= $entry_id ?>.cleanPreviewContainer();
    if (progress_idicator_needed)
    {
      ajaxBabbleEntry<?= $entry_id ?>.startProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.comments_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
    }
    
    pagination_number = pagination_number ? pagination_number : '0'; 
    if (pagination_number == '0') {
      final_url = ajaxBabbleEntry<?= $entry_id ?>.comments_template_url + ajaxBabbleEntry<?= $entry_id ?>.entry_id + '/';
    }
    else {
      final_url = ajaxBabbleEntry<?= $entry_id ?>.comments_template_url + ajaxBabbleEntry<?= $entry_id ?>.entry_id + '/' + ajaxBabbleEntry<?= $entry_id ?>.pagination_symbol + pagination_number;
    }
    
    ajaxBabbleEntry<?= $entry_id ?>.XHR = ajaxBabbleEntry<?= $entry_id ?>.createXHRObject();
    
    data = 'pagination_number=' + encodeURIComponent(pagination_number)
         + '&segment_1=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_1)
         + '&segment_2=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_2)
         + '&segment_3=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_3)
         + '&segment_4=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_4)
         + '&segment_5=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_5)
         + '&segment_6=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_6)
         + '&segment_7=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_7)
         + '&segment_8=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_8)
         + '&segment_9=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_9)
         + '&url_title=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.url_title)
         + '&css_id_to_scroll=' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.css_id_to_scroll)
         + '&XID=' + encodeURIComponent(ajaxBabbleEmbedEntry<?= $entry_id ?>.xid_hash)
         + '&csrf_token=' + encodeURIComponent(ajaxBabbleEmbedEntry<?= $entry_id ?>.xid_hash);
    
    if (certain_comment_id) {
      data += '&certain_comment_id=' + encodeURIComponent(certain_comment_id);
    }
    
    ajaxBabbleEntry<?= $entry_id ?>.XHR.open("POST", final_url, true);
    ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
    ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("X_REQUESTED_WITH", "XMLHttpRequest");
    ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Content-length", "data.length");
    ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Connection", "close");
    ajaxBabbleEntry<?= $entry_id ?>.XHR.send(data);
    ajaxBabbleEntry<?= $entry_id ?>.XHR.onreadystatechange = function() { 
      if (ajaxBabbleEntry<?= $entry_id ?>.XHR.readyState == 4) {
        ajaxBabbleEntry<?= $entry_id ?>.resultOfFetchComments();
        if (ajaxBabbleEntry<?= $entry_id ?>.add_callback_paginate) {
          for (var i = 0; i < ajaxBabbleEntry<?= $entry_id ?>.add_callback_paginate.length; i++) {
            if (ajaxBabbleEntry<?= $entry_id ?>.add_callback_paginate[i]) {
              ajaxBabbleEntry<?= $entry_id ?>.add_callback_paginate[i](ajaxBabbleEntry<?= $entry_id ?>.add_callback_paginate_args[i]);
            }
          }
        }
      }
    }
  },
  
  resultOfFetchComments: function(new_comment) {
    var comments_container;
    var response;
    var error_encountered;
    var error_message;   
    
    comments_container = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.comments_container_id);
    
    if (ajaxBabbleEntry<?= $entry_id ?>.XHR.status != 200) {
  			response = "An error was encountered: " + ajaxBabbleEntry<?= $entry_id ?>.XHR.status;
     error_encountered = true;
  		}
    
    if (ajaxBabbleEntry<?= $entry_id ?>.XHR.status == 200 && ajaxBabbleEntry<?= $entry_id ?>.comment_moderate == 'y') {
      ajaxBabbleEntry<?= $entry_id ?>.endProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.comments_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
      ajaxBabbleEntry<?= $entry_id ?>.cleanTextarea();
      alert(ajaxBabbleEntry<?= $entry_id ?>.comment_moderate_alert_text);
      return;
    }
    
    response = ajaxBabbleEntry<?= $entry_id ?>.XHR.responseText;
    
    if (response == 'null' || response == '') {
  			response = 'No response from server.';
     error_encountered = true;
  		}


    
    if (error_encountered != true) {
      ajaxBabbleEntry<?= $entry_id ?>.endProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.comments_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
      error_message = ajaxBabbleEntry<?= $entry_id ?>.parseResponseForErrorMessage(response);
      if (!error_message) {
        comments_container.innerHTML = response;
        if (new_comment) {
          ajaxBabbleEntry<?= $entry_id ?>.cleanTextarea();
        }
        ajaxBabbleEntry<?= $entry_id ?>.scrollCommentIntoView();
        ajaxBabbleEntry<?= $entry_id ?>.parseScript(response);
      }
      else {
        // Clean textarea in cases comment should be moderated or system considers that comment is spam
        if (error_message.indexOf('<?= $this->spam_indicator_phrase ?>') != -1 || error_message.indexOf('<?= $this->moderation_indicator_phrase ?>') != -1) {
          ajaxBabbleEntry<?= $entry_id ?>.cleanTextarea();
        }
        ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, error_message);
      }
    }
    else {
      ajaxBabbleEntry<?= $entry_id ?>.endProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.comments_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class); 
      error_message = ajaxBabbleEntry<?= $entry_id ?>.parseResponseForErrorMessage(response);
      if (!error_message) {
        ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, response);
      }
      else {
        ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, error_message);
      }
    }
  },
  
  loadNewlySubmittedComment: function(e) {
    var final_url;
    var data;
    var pagination_number;
    var comment_form;
    var error_occured;
    var get_string;
    var url_segments_exist;
    
    comment_form = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.form_id);
      
    ajaxBabbleEntry<?= $entry_id ?>.stopEvent(e);
    
    if (!ajaxBabbleEmbedEntry<?= $entry_id ?>.sort || ajaxBabbleEmbedEntry<?= $entry_id ?>.sort == 'desc') {
      pagination_number = 0;
    }
    if (ajaxBabbleEmbedEntry<?= $entry_id ?>.sort == 'asc') {
      pagination_number = (parseInt(ajaxBabbleEmbedEntry<?= $entry_id ?>.comments_number)) - (parseInt(ajaxBabbleEmbedEntry<?= $entry_id ?>.comments_number) % parseInt(ajaxBabbleEmbedEntry<?= $entry_id ?>.limit));
    }
    
    final_url = ajaxBabbleEntry<?= $entry_id ?>.comments_template_url + '<?= $entry_id ?>/';
    url_segments_exist = false;
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_1) {
      final_url += encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_1);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_2) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_2);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_3) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_3);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_4) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_4);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_5) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_5);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_6) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_6);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_7) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_7);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_8) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_8);
      url_segments_exist = true;
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.segment_9) {
      final_url += '--__--' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.segment_9);
      url_segments_exist = true;
    }
    if (!url_segments_exist) {
      final_url += 'no_url_segments';
    }
    if (ajaxBabbleEntry<?= $entry_id ?>.css_id_to_scroll) {
      final_url += '/' + encodeURIComponent(ajaxBabbleEntry<?= $entry_id ?>.css_id_to_scroll);
    }
    else {
      final_url += '/none';
    }
    final_url += '/' + ajaxBabbleEntry<?= $entry_id ?>.pagination_symbol + pagination_number;         
    
    ajaxBabbleEntry<?= $entry_id ?>.XHR = ajaxBabbleEntry<?= $entry_id ?>.createXHRObject();
    
    data = 'comment_submitted=yes';
    for (var i = 0; i < comment_form.elements.length; i++) {
      if (comment_form.elements[i].name == 'comment' && ajaxBabbleEntry<?= $entry_id ?>.trim(comment_form.elements[i].value) == '') {
        error_occured = true;
        break;
      }
      else if (comment_form.elements[i].name == 'RET') {
        data += '&' + comment_form.elements[i].name + '=' + encodeURIComponent(final_url);
      }
      else if (comment_form.elements[i].name != 'ACT' && comment_form.elements[i].name != 'preview') {
        data += '&' + comment_form.elements[i].name + '=' + encodeURIComponent(comment_form.elements[i].value);
      }
    }
    
    if (error_occured) {
      if (ajaxBabbleEntry<?= $entry_id ?>.empty_comment_message_text) {
        if (ajaxBabbleEntry<?= $entry_id ?>.empty_comment_display_js_alert) {
          alert(ajaxBabbleEntry<?= $entry_id ?>.empty_comment_message_text);
        }
        else {
          ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.preview_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
          ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
          ajaxBabbleEntry<?= $entry_id ?>.cleanPreviewContainer();
          ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, ajaxBabbleEntry<?= $entry_id ?>.empty_comment_message_text);
        }
      }
    }
    else {
      ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.preview_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
      ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
      ajaxBabbleEntry<?= $entry_id ?>.cleanPreviewContainer();
      ajaxBabbleEntry<?= $entry_id ?>.startProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.comments_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
      
      ajaxBabbleEntry<?= $entry_id ?>.XHR.open("POST", final_url, true);
      ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
      ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("X_REQUESTED_WITH", "XMLHttpRequest");
      ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Connection", "close");
  	   ajaxBabbleEntry<?= $entry_id ?>.XHR.send(data);
      ajaxBabbleEntry<?= $entry_id ?>.XHR.onreadystatechange = function() { 
        if (ajaxBabbleEntry<?= $entry_id ?>.XHR.readyState == 4) {
          ajaxBabbleEntry<?= $entry_id ?>.resultOfFetchComments(true);
          if (ajaxBabbleEntry<?= $entry_id ?>.add_callback_submit) {
            for (var i = 0; i < ajaxBabbleEntry<?= $entry_id ?>.add_callback_submit.length; i++) {
              ajaxBabbleEntry<?= $entry_id ?>.add_callback_submit[i](ajaxBabbleEntry<?= $entry_id ?>.add_callback_submit_args[i]);
            }
          }
        }
      } 
    }
     
  },
  
  parseResponseForErrorMessage: function(response) {
    var error_message_pattern;
    var redir_link_pattern;
    var redir_link;
    var message_part;
    var message_part_cleaned;
    var message;
    var title_pattern; 
    var title;
    
    title_pattern = /<title>\s*(.+)\s*<\/title>/;
    title = title_pattern.exec(response);
    if (title && typeof title[1] != 'undefined' && (title[1] == 'Error' || title[1] == 'System Offline')) {
      error_message_pattern = /<li>\s*(.+)\s*<\/li>/g;
      message = '';
      while (message_part = error_message_pattern.exec(response)) {
        message += message_part[0];
      }
      message = message.replace(/<li>/g, '<p>');
      message =  message.replace(/<\/li>/g, '</p>');
      redir_link_pattern = /<p><a\s*(.+)\s*<\/a><\/p>/g;
      redir_link = redir_link_pattern.exec(response);
      redir_link = redir_link ? redir_link[0] : '';
      error_message_pattern = /<p>(.+)/g;
      while (message_part = error_message_pattern.exec(response)) {
        if (message_part[0] != redir_link) {
          message_part_cleaned = message_part[0].replace(/<br\s\/>/g, '');
          message += message_part_cleaned;
        }
      }
      return message;
    }
    
    return false;
  },
  
  parseScript: function(_source) {
  		var source = _source;
  		var scripts = new Array();
    
  		// Strip out tags
  		while(source.indexOf("<script") > -1 || source.indexOf("</script") > -1) {
  			var s = source.indexOf("<script");
  			var s_e = source.indexOf(">", s);
  			var e = source.indexOf("</script", s);
  			var e_e = source.indexOf(">", e);
  			
  			scripts.push(source.substring(s_e+1, e));
  			source = source.substring(0, s) + source.substring(e_e+1);
  		}

  		for(var i=0; i<scripts.length; i++) {
     try {
  				eval(scripts[i]);
  			}
  			catch(ex) {
  				// do what you want here when a script fails
  			}
  		}
  		
  		return source;
	 },
  
  scrollCommentIntoView: function() {
    var comment_to_scroll;
    
    if (ajaxBabbleEntry<?= $entry_id ?>.css_id_to_scroll) {
      comment_to_scroll = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.css_id_to_scroll);
      if (comment_to_scroll) {
        comment_to_scroll.scrollIntoView();
      }
    }
  },
  
  cleanTextarea: function() {
    var comment_form;
    
    comment_form = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.form_id);
    for (var i = 0; i < comment_form.elements.length; i++) {
      if (comment_form.elements[i].name == 'comment') {
        comment_form.elements[i].value = '';
        break;
      }
    }
  },
  
  cleanPreviewContainer: function() {
    var preview_container;
    
    preview_container = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.preview_container_id);
    if (preview_container) {
      preview_container.innerHTML = '';
    }
  },
  
  startProgressIndicator: function(progress_indicator_id, progress_indicator_class) {
    var progress_indicator;
    
    progress_indicator = document.getElementById(progress_indicator_id);
    if (progress_indicator) {
      ajaxBabbleEntry<?= $entry_id ?>.addClassName(progress_indicator, progress_indicator_class, true);
      progress_indicator.scrollIntoView();
    }
  },
  
  endProgressIndicator: function(progress_indicator_id, progress_indicator_class) {
    var progress_indicator;
    
    progress_indicator = document.getElementById(progress_indicator_id);
    if (progress_indicator) {
      ajaxBabbleEntry<?= $entry_id ?>.removeClassName(progress_indicator, progress_indicator_class);
    }
  },
  
  displayErrorMessage: function(error_message_container_id, error_message_container_class, message) {
    var error_message_container;
    var message_div;
    
    error_message_container = document.getElementById(error_message_container_id);
    if (error_message_container) {
      ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(error_message_container_id, error_message_container_class);
      message_div = document.createElement('div');
      message_div.innerHTML = message;
      message_div.id = error_message_container_id + '_div';
      error_message_container.appendChild(message_div);
      error_message_container.scrollIntoView();
      ajaxBabbleEntry<?= $entry_id ?>.addClassName(error_message_container, error_message_container_class, true);
    }
  },
  
  removeErrorMessage: function(error_message_container_id, error_message_container_class) {
    var error_message_container;
    var message_div;
    
    error_message_container = document.getElementById(error_message_container_id);
    message_div = document.getElementById(error_message_container_id + '_div');
    if (error_message_container && message_div) {
      ajaxBabbleEntry<?= $entry_id ?>.removeClassName(error_message_container, error_message_container_class);
      error_message_container.removeChild(message_div);
    }
  },
  
  loadCommentPreview: function(e) {
    var final_url;
    var data;
    var comment_form;
    var error_occured;
    
    comment_form = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.form_id);
      
    ajaxBabbleEntry<?= $entry_id ?>.stopEvent(e);
    
    final_url = ajaxBabbleEntry<?= $entry_id ?>.preview_template_url;
    
    ajaxBabbleEntry<?= $entry_id ?>.XHR = ajaxBabbleEntry<?= $entry_id ?>.createXHRObject();
    
    data = 'comment_preview=yes';
    
    for (var i = 0; i < comment_form.elements.length; i++) {
      if (comment_form.elements[i].name == 'comment' && ajaxBabbleEntry<?= $entry_id ?>.trim(comment_form.elements[i].value) == '') {
        error_occured = true;
        break;
      }
      else if (comment_form.elements[i].name != 'ACT' && comment_form.elements[i].name != 'submit') {
        data += '&' + comment_form.elements[i].name + '=' + encodeURIComponent(comment_form.elements[i].value);
      }
    }
    
    if (error_occured) {
      if (ajaxBabbleEntry<?= $entry_id ?>.empty_comment_display_js_alert) {
        alert(ajaxBabbleEntry<?= $entry_id ?>.empty_comment_message_text);
      }
    }
    else {
      ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
      ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.preview_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
      ajaxBabbleEntry<?= $entry_id ?>.cleanPreviewContainer();
      ajaxBabbleEntry<?= $entry_id ?>.startProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.preview_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
      
      ajaxBabbleEntry<?= $entry_id ?>.XHR.open("POST", final_url, true);
      ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
      ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("X_REQUESTED_WITH", "XMLHttpRequest");
      ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Connection", "close");
  	   ajaxBabbleEntry<?= $entry_id ?>.XHR.send(data);
      ajaxBabbleEntry<?= $entry_id ?>.XHR.onreadystatechange = function () {
        if (ajaxBabbleEntry<?= $entry_id ?>.XHR.readyState == 4) {
          ajaxBabbleEntry<?= $entry_id ?>.resultOfCommentsPreview(ajaxBabbleEntry<?= $entry_id ?>.preview_container_id);
        }
      }
    }
  },
  
  resultOfCommentsPreview: function() {
    var preview_container;
    var response;
    var error_encountered;
    
    preview_container = document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.preview_container_id);
    
    if (ajaxBabbleEntry<?= $entry_id ?>.XHR.status != 200) {
  			response = "An error was encountered: " + ajaxBabbleEntry<?= $entry_id ?>.XHR.status;
     error_encountered = true;
  		}
    
    response = ajaxBabbleEntry<?= $entry_id ?>.XHR.responseText;
    
    if (response == 'null') {
  			response = 'No response from server.';
     error_encountered = true;
  		}
    
    if (error_encountered != true) {
      ajaxBabbleEntry<?= $entry_id ?>.endProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.preview_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
      error_message = ajaxBabbleEntry<?= $entry_id ?>.parseResponseForErrorMessage(response);
      if (!error_message) {
        preview_container.innerHTML = response;
        preview_container.scrollIntoView();
      }
      else {
        ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.preview_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, error_message);
      }
    }
    else {
      ajaxBabbleEntry<?= $entry_id ?>.endProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.preview_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
    }
  },
  
  deleteComment: function(params) {
    var data;
    var delete_confirm;
    var comment_el;
    
    if (params.alert_text) {
      delete_confirm = confirm(params.alert_text);
      if (delete_confirm) {
        ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.preview_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
        ajaxBabbleEntry<?= $entry_id ?>.removeErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class);
        ajaxBabbleEntry<?= $entry_id ?>.cleanPreviewContainer();
        ajaxBabbleEntry<?= $entry_id ?>.endProgressIndicator(ajaxBabbleEntry<?= $entry_id ?>.comments_progress_indicator_id, ajaxBabbleEntry<?= $entry_id ?>.progress_indicator_class);
        
        // hide comment to be deleted
        comment_el = document.getElementById(params.comment_html_id);
        if (comment_el) {
          comment_el.style.display = 'none';  
        }
        
        ajaxBabbleEntry<?= $entry_id ?>.XHR = ajaxBabbleEntry<?= $entry_id ?>.createXHRObject();
        
        data = 'delete_comment_id=' + encodeURIComponent(params.delete_comment_id)
             + '&entry_id=' + encodeURIComponent(params.entry_id)
             + '&limit=' + encodeURIComponent(params.limit)
             + '&sort=' + encodeURIComponent(params.sort)
             + '&status=' + encodeURIComponent(params.status)
             + '&orderby=' + encodeURIComponent(params.orderby)
             + '&XID=' + encodeURIComponent(ajaxBabbleEmbedEntry<?= $entry_id ?>.xid_hash)
             + '&csrf_token=' + encodeURIComponent(ajaxBabbleEmbedEntry<?= $entry_id ?>.xid_hash);
        
        ajaxBabbleEntry<?= $entry_id ?>.XHR.open("POST", params.delete_template_url, true);
        ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("X_REQUESTED_WITH", "XMLHttpRequest");
        ajaxBabbleEntry<?= $entry_id ?>.XHR.setRequestHeader("Connection", "close");
        ajaxBabbleEntry<?= $entry_id ?>.XHR.send(data);
        ajaxBabbleEntry<?= $entry_id ?>.XHR.onreadystatechange = function() {
          if (ajaxBabbleEntry<?= $entry_id ?>.XHR.readyState == 4) {
            ajaxBabbleEntry<?= $entry_id ?>.resultOfDeleteComment(comment_el);
            if (ajaxBabbleEntry<?= $entry_id ?>.add_callback_delete) {
              for (var i = 0; i < ajaxBabbleEntry<?= $entry_id ?>.add_callback_delete.length; i++) {
                if (ajaxBabbleEntry<?= $entry_id ?>.add_callback_delete[i]) {
                  ajaxBabbleEntry<?= $entry_id ?>.add_callback_delete[i](ajaxBabbleEntry<?= $entry_id ?>.add_callback_delete_args[i]);
                }
              }
            }
          }
        }
      }
    }
  },
  
  resultOfDeleteComment: function(comment_el) {
    var response;
    var error_encountered;
    var message;
    var paginate_number;
    var response_array;
    
    if (ajaxBabbleEntry<?= $entry_id ?>.XHR.status != 200) {
  			response = "An error was encountered: " + ajaxBabbleEntry<?= $entry_id ?>.XHR.status;
     error_encountered = true;
  		}
    
    response = ajaxBabbleEntry<?= $entry_id ?>.trim(ajaxBabbleEntry<?= $entry_id ?>.XHR.responseText);
    
    if (response == 'null' || response == '') {
  			response = 'No response from server.';
     error_encountered = true;
  		}
    
    if (error_encountered != true) {
      response_array = response.split('--__--');
      if (response_array.length == 2) {
        message = response_array[0];
        paginate_number = response_array[1];
        if (message != 'deleted') {
          ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, message);
        }
        if (paginate_number != 'same_page') {
          ajaxBabbleEntry<?= $entry_id ?>.fetchComments(paginate_number, false, false);
        }
      }
    }
    else {
      // display comment which was not deleted
      comment_el.style.display = '';
      ajaxBabbleEntry<?= $entry_id ?>.displayErrorMessage(ajaxBabbleEntry<?= $entry_id ?>.comments_error_message_id, ajaxBabbleEntry<?= $entry_id ?>.error_message_class, response);
    }
  },
  
  process_init: function() {
    //alert('AJAX Babble process_init, init.done: ' + form_<?= @$form_id ?>_init.attached);
    if (!form_<?= @$form_id ?>_init.done) {
      for (var i = 0; i < form_<?= @$form_id ?>_init.functions.length; i++) {
        form_<?= @$form_id ?>_init.functions[i]();
      }
      form_<?= @$form_id ?>_init.done = true;
    }
    if (!form_<?= @$form_id ?>_button.submit_button_old_funcs_attached && form_<?= @$form_id ?>_button.submit_button_old && (form_<?= @$form_id ?>_button.submit_button_old_funcs.length > 0 || form_<?= @$form_id ?>_button.submit_button_old_funcs_deferred.length > 0)) {
      ajaxBabbleEntry<?= $entry_id ?>.addEvent(
        form_<?= @$form_id ?>_button.submit_button_old, 
        'click', 
        function(e) {
          for (var i = 0; i < form_<?= @$form_id ?>_button.submit_button_old_funcs.length; i++) {
            form_<?= @$form_id ?>_button.submit_button_old_funcs[i](e);
          }
          for (var i = 0; i < form_<?= @$form_id ?>_button.submit_button_old_funcs_deferred.length; i++) {
            form_<?= @$form_id ?>_button.submit_button_old_funcs_deferred[i](e);
          }
        }, 
        false
      );
      form_<?= @$form_id ?>_button.submit_button_old_funcs_attached = true;
    }
    if (!form_<?= @$form_id ?>_button.submit_button_clone_funcs_attached && form_<?= @$form_id ?>_button.submit_button_clone && (form_<?= @$form_id ?>_button.submit_button_clone_funcs.length > 0 || form_<?= @$form_id ?>_button.submit_button_clone_funcs_deferred.length > 0)) {
      ajaxBabbleEntry<?= $entry_id ?>.addEvent(
        form_<?= @$form_id ?>_button.submit_button_clone, 
        'click', 
        function(e) {
          for (var i = 0; i < form_<?= @$form_id ?>_button.submit_button_clone_funcs.length; i++) {
            form_<?= @$form_id ?>_button.submit_button_clone_funcs[i](e);
          }
          for (var i = 0; i < form_<?= @$form_id ?>_button.submit_button_clone_funcs_deferred.length; i++) {
            form_<?= @$form_id ?>_button.submit_button_clone_funcs_deferred[i](e);
          }
        }, 
        false
      );
      form_<?= @$form_id ?>_button.submit_button_clone_funcs_attached = true;
    }
  },
  
  initLoadNewlySubmittedComment: function() {
    //alert('initLoadNewlySubmittedComment started!');
    form_<?= @$form_id ?>_button.submit_button = document.getElementById(form_<?= @$form_id ?>_button.submit_button_id);
    if (!form_<?= @$form_id ?>_button.submit_button) {
      alert('Submit button of the comment form not found!')
    }
    if (form_<?= @$form_id ?>_button.submit_button) {
      if (!form_<?= @$form_id ?>_button.submit_button_old) {
        form_<?= @$form_id ?>_button.submit_button_old = form_<?= @$form_id ?>_button.submit_button;
      }
      form_<?= @$form_id ?>_button.submit_button_old_funcs.push(ajaxBabbleEntry<?= $entry_id ?>.loadNewlySubmittedComment);
    }
  },
  
  initLoadCommentPreview: function() {
    if (document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.preview_button_id)) {
      ajaxBabbleEntry<?= $entry_id ?>.addEvent(document.getElementById(ajaxBabbleEntry<?= $entry_id ?>.preview_button_id), 'click', ajaxBabbleEntry<?= $entry_id ?>.loadCommentPreview, false);
    }
  }  

}

form_<?= @$form_id ?>_init.functions.push(ajaxBabbleEntry<?= $entry_id ?>.initLoadNewlySubmittedComment, ajaxBabbleEntry<?= $entry_id ?>.initLoadCommentPreview);
ajaxBabbleEntry<?= $entry_id ?>.addEvent(window, "load", ajaxBabbleEntry<?= $entry_id ?>.process_init, false);

//]]>
</script>

<?php
 		$javascript = ob_get_contents();
 		ob_end_clean();
   
   if ($protect_backslashes)
   {
     $javascript = str_replace('\\', '\\\\', $javascript);
   }
   
   return $javascript;
 }
 // END FUNCTION
 
  function delete_link()
  {    
    $this->EE =& get_instance();
    
    // fetch parameters
    $comment_id = $this->EE->TMPL->fetch_param('comment_id');
    $comment_html_id = $this->EE->TMPL->fetch_param('comment_html_id');
    $entry_id = $this->EE->TMPL->fetch_param('entry_id');
    $delete_template_url = $this->EE->TMPL->fetch_param('delete_template_url');
    $class = $this->EE->TMPL->fetch_param('class');
    $link_text = $this->EE->TMPL->fetch_param('link_text') ? $this->EE->TMPL->fetch_param('link_text') : 'Delete';
    $alert_text = $this->EE->TMPL->fetch_param('alert_text') ? str_replace('\\\\', '', addslashes($this->EE->TMPL->fetch_param('alert_text'))) : 'Do you really want to delete this comment?';
    $limit = $this->EE->TMPL->fetch_param('limit') ? $this->EE->TMPL->fetch_param('limit') : 100;
    $sort = $this->EE->TMPL->fetch_param('sort') ? $this->EE->TMPL->fetch_param('sort') : 'desc';
    $orderby = $this->EE->TMPL->fetch_param('orderby') ? $this->EE->TMPL->fetch_param('orderby') : 'date';
    $status = $this->EE->TMPL->fetch_param('status');
    
    // define variables
    $error_occured = FALSE;
    $delete_link = '';
    $add_on_name = get_class($this);
    
    if (!$comment_id)
    {
      echo 'ERORR! "comment_id" parameter of ext:ajax_babble:delete_link tag must be defined!'.'<br><br>'.PHP_EOL;
      $error_occured = TRUE;
    }
    if (!$comment_html_id)
    {
      echo 'ERORR! "comment_html_id" parameter of ext:ajax_babble:delete_link tag must be defined!'.'<br><br>'.PHP_EOL;
      $error_occured = TRUE;
    }
    if (!$entry_id)
    {
      echo 'ERORR! "entry_id" parameter of ext:ajax_babble:delete_link tag must be defined!'.'<br><br>'.PHP_EOL;
      $error_occured = TRUE;
    }
    if (!$delete_template_url)
    {
      echo 'ERORR! "delete_template_url" parameter of ext:ajax_babble:delete_link tag must be defined!'.'<br><br>'.PHP_EOL;
      $error_occured = TRUE;
    }
    
    if (!$error_occured)
    {      
      if (!isset($this->EE->session->cache[$add_on_name]))
      {
        $this->EE->session->cache[$add_on_name] = array();
      }
      
      // the case logged-in user has no rights to delete comments
      if ($this->EE->session->userdata['group_id'] != 1 AND $this->EE->session->userdata['can_delete_own_comments'] != 'y' AND $this->EE->session->userdata['can_delete_all_comments'] != 'y')
      {
        return '';
      }
      
      // the case logged-in user has the right delete comments;
      
      //form URL of the delete template 
      if (!isset($this->EE->session->cache[$add_on_name]['delete_template_url'][$entry_id]) OR !$this->EE->session->cache[$add_on_name]['delete_template_url'][$entry_id])
      {
        $this->EE->session->cache[$add_on_name]['delete_template_url'][$entry_id] = $this->_template_url($delete_template_url);
      }
      
      // form delete link HTML
      $delete_link = '<a onclick="ajaxBabbleEntry'.$entry_id.'.deleteComment({delete_comment_id: '.$comment_id.',
                                                                               comment_html_id: \''.$comment_html_id.'\',
                                                                               entry_id: \''.$entry_id.'\',
                                                                               delete_template_url: \''.$this->EE->session->cache[$add_on_name]['delete_template_url'][$entry_id].'\',
                                                                               alert_text: \''.$alert_text.'\',
                                                                               limit: \''.$limit.'\',
                                                                               sort: \''.$sort.'\',
                                                                               status: \''.$status.'\',
                                                                               orderby: \''.$orderby.'\'})" ';
      if ($class)
      {
        $delete_link .= ' class="'.$class.'" ';
      }
      $delete_link .= '>';
      $delete_link .= $link_text;
      $delete_link .= '</a>'; 
      
      // find all comments of the entry logged-in user has right to delete
      if($this->EE->session->userdata['group_id'] == 1 OR $this->EE->session->userdata['can_delete_all_comments'] == 'y') // user can delete any comment
      {
        return $delete_link;
      }
      elseif ($this->EE->session->userdata['can_delete_own_comments'] == 'y') // user can delete comment in entries he has authored
      {
        // if there is no stored info about users own entries, find them 
        if (!isset($this->EE->session->cache[$add_on_name]['own_entries']))
        {
          $this->EE->session->cache[$add_on_name]['own_entries'] = array();
          $sql_own_entries = "SELECT GROUP_CONCAT(entry_id SEPARATOR '|') as entry_id_numbers 
                              FROM exp_channel_titles 
                              WHERE author_id != '0' AND author_id = '".$this->EE->session->userdata['member_id']."' AND site_id = '".$this->EE->config->item('site_id')."'";
          $query_own_entries = $this->EE->db->query($sql_own_entries);
          if ($query_own_entries->num_rows() == 1)
          {
            $this->EE->session->cache[$add_on_name]['own_entries'] = explode('|', $query_own_entries->row('entry_id_numbers'));
          }
        }
        if (in_array($entry_id, $this->EE->session->cache[$add_on_name]['own_entries']))
        {
          return $delete_link;
        }
      }
      // user can delete his own comments
      // if there is no stored info about users own comments, find them
      if (!isset($this->EE->session->cache[$add_on_name]['own_comments'][$entry_id]))
      {
        $this->EE->session->cache[$add_on_name]['own_comments'][$entry_id] = array();
        $sql_own_comments = "SELECT GROUP_CONCAT(comment_id SEPARATOR '|') as comment_id_numbers 
                             FROM exp_comments 
                             WHERE author_id != '0' AND author_id = '".$this->EE->session->userdata['member_id']."' AND entry_id = '".$entry_id."'";
        $query_own_comments = $this->EE->db->query($sql_own_comments);
        //print_r($query_own_comments);
        if ($query_own_comments->num_rows() == 1)
        {
          $this->EE->session->cache[$add_on_name]['own_comments'][$entry_id] = explode('|', $query_own_comments->row('comment_id_numbers'));
        }
      }
      if (in_array($comment_id, $this->EE->session->cache[$add_on_name]['own_comments'][$entry_id]))
      {
        return $delete_link;
      }
    }
  }
  // END FUNCTION
  
  function delete_comment()
  {
    $this->EE =& get_instance();
    
    $no_permission_msg = $this->EE->TMPL->fetch_param('no_permission_msg') ? str_replace('\\\\', '', addslashes($this->EE->TMPL->fetch_param('no_permission_msg'))) : "You\'re not permitted to delete this comment.";
    $comment_not_found_msg = $this->EE->TMPL->fetch_param('comment_not_found_msg') ? str_replace('\\\\', '', addslashes($this->EE->TMPL->fetch_param('comment_not_found_msg'))) : "The comment does not exist or has been already deleted.";
    
    $delimiter = '--__--';
    $msg = '';
    $paginate_number = '';
    $delete_success_msg = 'deleted';
    $same_page_msg = 'same_page';
    $add_on_name = get_class($this);
    
    $comment_id = $this->EE->input->post('delete_comment_id');
    $entry_id = $this->EE->input->post('entry_id');
    $per_page = $this->EE->input->post('limit');
    $sort = $this->EE->input->post('sort');
    $status = $this->EE->input->post('status');
    $orderby = $this->EE->input->post('orderby');
    
    // check comment_id
    if(! (is_numeric($comment_id) AND is_int($comment_id + 0)) )
    {
      $msg = $comment_not_found_msg;
      $paginate_number = $same_page_msg;
      return $msg.$delimiter.$paginate_number;
    }
    
    // find comment info
    $sql_comment_info = "SELECT exp_comments.author_id AS comment_author_id, exp_channel_titles.author_id AS entry_author_id, exp_channel_titles.channel_id, exp_members.total_comments AS comment_author_total_comments 
                         FROM 
                           exp_comments
                             INNER JOIN
                           exp_channel_titles
                             ON
                           exp_channel_titles.entry_id = exp_comments.entry_id
                             LEFT OUTER JOIN
                           exp_members
                             ON
                           exp_comments.author_id = exp_members.member_id 
                         WHERE exp_comments.comment_id = '".$comment_id."' AND exp_channel_titles.entry_id = '".$entry_id."'   
                         LIMIT 1 ";
    $query_comment_info = $this->EE->db->query($sql_comment_info);
    
    // comment does not exist
    if ($query_comment_info->num_rows() != 1)
    {
      $msg = $comment_not_found_msg;
      $paginate_number = $same_page_msg;
      return $msg.$delimiter.$paginate_number;
    }
    
    // comment exists
    if ($query_comment_info->num_rows() == 1)
    {      
      if (!isset($this->EE->session->cache[$add_on_name]))
      {
        $this->EE->session->cache[$add_on_name] = array();
      }
      
      $comment_author_id = $query_comment_info->row('comment_author_id');
      $entry_author_id = $query_comment_info->row('entry_author_id');
      $weblog_id = $query_comment_info->row('channel_id');
      $comment_author_total_comments = $query_comment_info->row('comment_author_total_comments');

      // user can delete any comment
      if($this->EE->session->userdata['group_id'] == 1 OR $this->EE->session->userdata['can_delete_all_comments'] == 'y')
      {
        // recalculating pagination number 
        $paginate_number = $this->_recalculate_pagination_number($comment_id, $entry_id, $sort, $orderby, $per_page, $status);
        // deleting comment
        $this->_process_delete_comment($comment_id, $weblog_id, $comment_author_id, $comment_author_total_comments, $entry_id);
        $msg = $delete_success_msg;
        return $msg.$delimiter.$paginate_number;
      }
      // user can delete comment in entries he has authored
      elseif ($this->EE->session->userdata['can_delete_own_comments'] == 'y')
      {
        // if there is no stored info about users own entries, find them 
        if (!isset($this->EE->session->cache[$add_on_name]['own_entries']))
        {
          $this->EE->session->cache[$add_on_name]['own_entries'] = array();
          $sql_own_entries = "SELECT GROUP_CONCAT(entry_id SEPARATOR '|') as entry_id_numbers 
                              FROM exp_channel_titles 
                              WHERE author_id != '0' AND author_id = '".$this->EE->session->userdata['member_id']."' AND site_id = '".$this->EE->config->item('site_id')."'";
          $query_own_entries = $this->EE->db->query($sql_own_entries);
          if ($query_own_entries->num_rows() == 1)
          {
            $this->EE->session->cache[$add_on_name]['own_entries'] = explode('|', $query_own_entries->row('entry_id_numbers'));
          }
        }
        if (in_array($entry_id, $this->EE->session->cache[$add_on_name]['own_entries']))
        {
          // recalculating pagination number 
          $paginate_number = $this->_recalculate_pagination_number($comment_id, $entry_id, $sort, $orderby, $per_page, $status);
          // deleting comment
          $this->_process_delete_comment($comment_id, $weblog_id, $comment_author_id, $comment_author_total_comments, $entry_id);
          $msg = $delete_success_msg;
          return $msg.$delimiter.$paginate_number;
        }
      }
      // user can delete his own comments
      // if there is no stored info about users own comments, find them
      
      if (!isset($this->EE->session->cache[$add_on_name]['own_comments'][$entry_id]))
      {
        $this->EE->session->cache[$add_on_name]['own_comments'][$entry_id] = array();
        $sql_own_comments = "SELECT GROUP_CONCAT(comment_id SEPARATOR '|') as comment_id_numbers 
                             FROM exp_comments 
                             WHERE author_id != '0' AND author_id = '".$this->EE->session->userdata['member_id']."' AND entry_id = '".$entry_id."'";
        $query_own_comments = $this->EE->db->query($sql_own_comments);
        if ($query_own_comments->num_rows() == 1)
        {
          $this->EE->session->cache[$add_on_name]['own_comments'][$entry_id] = explode('|', $query_own_comments->row('comment_id_numbers'));
        }
      }
      if (in_array($comment_id, $this->EE->session->cache[$add_on_name]['own_comments'][$entry_id]))
      {
        // recalculating pagination number 
        $paginate_number = $this->_recalculate_pagination_number($comment_id, $entry_id, $sort, $orderby, $per_page, $status);
        // deleting comment
        $this->_process_delete_comment($comment_id, $weblog_id, $comment_author_id, $comment_author_total_comments, $entry_id);
        $msg = $delete_success_msg;
        return $msg.$delimiter.$paginate_number;
      }
      
      // user has no permission to delete comment 
      if ($msg != $delete_success_msg)
      {
        $msg = $no_permission_msg;
        $paginate_number = $same_page_msg;
        return $msg.$delimiter.$paginate_number;
      }
    }
  }
  // END FUNCTION
  
  function _process_delete_comment($comment_id, $weblog_id, $comment_author_id, $comment_author_total_comments, $entry_id)
  {
    global $DB, $STAT;
    
    // delete comment
    $this->EE->db->query("DELETE FROM exp_comments WHERE comment_id = '".$comment_id."' ");
    
    // update total comments number of comment author
    $comment_author_total_comments--;
    $this->EE->db->query("UPDATE exp_members SET total_comments = '".$comment_author_total_comments."' WHERE member_id = '".$comment_author_id."' ");
    
    // update "comment_total" field of exp_channel_titles table
    $sql_find_comment_total = "SELECT comment_total FROM exp_channel_titles WHERE entry_id = '".$entry_id."' LIMIT 1 ";
    $query_find_comment_total = $this->EE->db->query($sql_find_comment_total);
    if ($query_find_comment_total->num_rows() == 1)
    {
      $comment_total = $query_find_comment_total->row('comment_total');
      if ($comment_total > 0)
      {
        $comment_total--;
        $sql_update_comment_total = "UPDATE exp_channel_titles SET comment_total = '".$comment_total."' WHERE entry_id = '".$entry_id."' ";
        $this->EE->db->query($sql_update_comment_total);
      }
    }
    
    // statistics update
    $this->EE->stats->update_channel_stats($weblog_id);
  		$this->EE->stats->update_comment_stats($weblog_id);
  }
  // END FUNCTION
  
  function _recalculate_pagination_number($comment_id_to_delete, $entry_id, $sort, $orderby, $per_page, $status)
  {
    // find all comments posted into entry
    if ($orderby == 'date')
    {
      $orderby = 'comment_date';
    }
    
    if (!$status)
    {
      $status_clause = " status != 'c' ";
    }
    else
    {
      $status_array = explode('|', $status);
      foreach ($status_array as $key => $val)
      {
        $status_array[$key] = substr($val, 0, 1);
      }
      $status_clause = " status IN ('".implode("','", $status_array)."') ";
    }
    
    $sql_comment_id_numbers = "SELECT comment_id 
                               FROM exp_comments 
                               WHERE entry_id = '".$entry_id."' AND ".$status_clause." 
                               ORDER BY ".$orderby." ".strtoupper($sort);
    $query_comment_id_numbers = $this->EE->db->query($sql_comment_id_numbers);
    if ($query_comment_id_numbers->num_rows() > 0)
    {
      $comment_id_numbers_array = array();
      $query_comment_id_numbers_result = $query_comment_id_numbers->result_array();
      foreach ($query_comment_id_numbers_result as $row)
      {
        array_push($comment_id_numbers_array, $row['comment_id']);
      }
      $key = array_search($comment_id_to_delete, $comment_id_numbers_array);
      
      // find current page
      $current_page = floor(($key/$per_page) + 1);
      
      // find if comment to be deleted is the last comment on the last page
      $last_comment = FALSE;
      if ($key == count($comment_id_numbers_array) - 1)
      {
        $last_comment = TRUE;
      }
      
      // find if comment to be deleted is the first comment on paginated page
      $comment_to_delete_absolute_count = $key + 1;
      $first_comment_on_page = FALSE;
      if ($comment_to_delete_absolute_count % $per_page == 1)
      {
        $first_comment_on_page = TRUE; 
      }
      
      // find if the comment to be deleted is the only comment on the last page
      // in such case we need load prevous paginated page
      if ($current_page > 1 AND $last_comment AND $first_comment_on_page)
      {
        $paginate_number = ($current_page - 2)*$per_page;
      }
      // comment to be deleted is not the only comment on the last page
      else 
      {
        $paginate_number = ($current_page - 1)*$per_page;
      }
    }
    return $paginate_number;
  }
  // END FUNCTION
  
  function pagination()
  {
    $this->EE =& get_instance();
    
    $tagdata = trim($this->EE->TMPL->tagdata);

    $pagination_symbol = $this->EE->TMPL->fetch_param('pagination_symbol') ? $this->EE->TMPL->fetch_param('pagination_symbol') : 'N';
    $entry_id = $this->EE->TMPL->fetch_param('entry_id'); // added by exp:ajax_pagination:wrapper tag
    
    if (!$entry_id)
    {
      echo 'ERORR! "entry_id" parameter of ext:ajax_babble:pagination tag must be defined!'.'<br><br>'.PHP_EOL;
    }

    if ($tagdata)
    {
      $tagdata = $this->_change_pagination_links($tagdata, $pagination_symbol, $entry_id);  
    }

    return $tagdata;
  }
  // END FUNCTION
  
  function _change_pagination_links($pagination_links, $pagination_symbol, $entry_id)
  {
    $pattern = '/href=\"(.*)\"/Usi';
    
    while (preg_match($pattern, $pagination_links, $matches))
    {
      $url = trim(@$matches[1], '/');
      $last_segment = end(explode('/', $url));
      $pos = strpos($last_segment, $pagination_symbol);
      if ($pos === 0)
      {
        $pagination_segment = substr($last_segment, strlen($pagination_symbol));
      }
      else
      {
        $pagination_segment = '';
      }
      //echo '$ajax_url: ['.$ajax_url.']<br><br>'.PHP_EOL;
      $onclick_param = 'onclick="ajaxBabbleEntry'.$entry_id.'.fetchComments(\''.$pagination_segment.'\', false, true);"';
      $pagination_links = preg_replace($pattern, $onclick_param, $pagination_links, 1);
    }
    
    return $pagination_links;
  }
  // END FUNCTION
    
// ----------------------------------------
//  Plugin Usage
// ----------------------------------------

// This function describes how the plugin is used.
//  Make sure and use output buffering

public static function usage()
{
ob_start(); 
?>

This plugin uses AJAX to submit comments. Supports pagination, deleting comments, scrolling newly submitted
comment into view and triggering search for certain comment through URL.
This add-on has been designed to be fully compatible with 
AJAX Captcha http://devot-ee.com/add-ons/ajax-captcha/,  
AJAX Form Validator http://devot-ee.com/add-ons/ajax-form-validator/,
AJAX Login http://devot-ee.com/add-ons/ajax-login/,
and and Edit Comments http://devot-ee.com/add-ons/edit-comments. 
Also it is compatible with rating module LikEE http://devot-ee.com/add-ons/likee/
(see USAGE WITH RATING MODULE LIKEE).

I. THE TAG exp:ajax_babble:script

This is a *single* tag.

PARAMETERS

1) form_id - required. Allows you to specify CSS id parameter of the form outputted by 
exp:comment:form tag. Usually its value will be "comment_form".

2) entry_id  - required. Allows you to specify ID number of the entry being commented on.

3) comments_template_url - required. Allows you to specify URL of the comment template.
You must specify full URL, i.e. starting with "http". This parameter accepts inside its value
the following ExpressionEngine variables: site_id, site_url, site_index, homepage.

4) comments_container_id - required. Allows you to specify CSS id parameter of the HTML element
inside which AJAX should output comments.

5) submit_button_id - required. Allows you to specify CSS id parameter of the submit button of the
comment form.

6) comments_number_id - optional. Allows you to specify CSS id parameter of the HTML element
inside which number of comments is displayed; this number will be updated when AJAX will submit a new
comment. This parameter supports pipe operator. 

7) css_id_to_scroll - optional. Allows you to specify CSS id parameter of the comment which will
be scrolled into view after AJAX will load comments. Into view will be scrolled either 
the newly submitted comment or the comment which was found by the search triggered by URL.
The value of this parameter *must* be the same as the value of 
identically named parameter of exp:ajax_babble:comments tag.

8) preview_template_url - optional. Allows you to specify URL of the preview template.
You must specify full URL, i.e. starting with "http". This parameter accepts inside its value
the following ExpressionEngine variables: site_id, site_url, site_index, homepage.

9) preview_container_id - optional. Allows you to specify CSS id parameter of the HTML element
inside which AJAX should output comment preview.

10) preview_button_id - optional. Allows you to specify CSS id parameter of the preview button of the
comment form.

11) comments_progress_indicator_id - optional. Allows you to specify CSS id parameter of the HTML element
which will act as the indicator of comments being loaded.

12) preview_progress_indicator_id - optional. Allows you to specify CSS id parameter of the HTML element
which will act as the indicator of comment preview being loaded.

13) progress_indicator_class - optional. Allows you to specify CSS class parameter of the HTML element
which will act as the indicator of comments or comment preview being loaded.

14) comments_error_message_id - optional. Allows you to specify CSS id parameter of the HTML element
inside which an error message outputted when AJAX tried to load comments will be displayed.

15) preview_error_message_id - optional. Allows you to specify CSS id parameter of the HTML element
inside which an error message outputted when AJAX tried to load comment preview will be displayed.

16) error_message_class - optional.  Allows you to specify CSS class parameter of the HTML element
inside which an error message outputted when AJAX tried to load comments or comment preview will be displayed.

17) add_callback_submit - optional. Allows you to specify name of javascript function which
will be executed when AJAX response will be outputted into container after submission of the new comment.
This parameters supports pipe character, i.e. you can add several callback functions.

18) add_callback_submit_args - optional. Allows you to specify argument of javascript function which will 
be executed after successful submission of comment. This parameter supports pipe operator, i.e. you can specify arguments 
of several javascript functions. The order of arguments should follow the order of functions in "add_callback_submit" parameter. 
If some functions should have arguments and other functions shouldn't, specify "null" or "0" (without quotation marks) as arguments for the latter.

19) add_callback_paginate - optional. Allows you to specify name of javascript function which
will be executed when AJAX response will be outputted into container after clicking of some pagination link.
This parameters supports pipe character, i.e. you can add several callback functions.

20) add_callback_paginate_args - optional. Allows you to specify argument of javascript function which will 
be executed after successful load of paginated comments. This parameter supports pipe operator, i.e. you can specify arguments 
of several javascript functions. The order of arguments should follow the order of functions in "add_callback_paginate" parameter. 
If some functions should have arguments and other functions shouldn't, specify "null" or "0" (without quotation marks) as arguments for the latter.

21) add_callback_delete - optional. Allows you to specify argument of javascript function which will 
be executed after successful deletion of a comment. This parameters supports pipe character, i.e. you can add several callback functions. 

22) add_callback_delete_args - optional. Allows you to specify argument of javascript function which will 
be executed after successful eletion of a comment. This parameter supports pipe operator, i.e. you can specify arguments 
of several javascript functions. The order of arguments should follow the order of functions in "add_callback_delete" parameter.
If some functions should have arguments and other functions shouldn't, specify "null" or "0" (without quotation marks) as arguments for the latter.

23) any name of javascript function which is used inside the value of "add_callback_submit" parameter or
"add_callback_paginate" parameter can be used as the name of a new parameter. The value of this parameter will
be used as argument of the relevant javascript function. E.g. if you have parameter add_callback_submit="my_callback", 
then you can add parameter my_callback="5"; the number "5" will be used as the argument for "my_calback" function.
In case you need to call a function with several arguments, use javascript object.  E.g. if you have parameter 
add_callback_submit="new_callback", then you can add parameter new_callback='{"value1": null,"value2": "some_string"}'.
Notice, that you can this way specify arguments only for functions defined in global namespace, i.e. you can define
agument for the function "my_cllback", but cannot define argument for the fumction "my_namespace.my_callback". To define 
arguments for namespaced functions use parameters "add_callback_submit_args" and "add_callback_paginate_args".

24) empty_comment_message_text - optional. Allows you to specify the text of the message which will
be displayed in case comment is empty.

25) empty_comment_display_js_alert - optional. Possible values is "yes" and "no". Default is "no". Allows you to specify
if you wish empty comment message to be displayed as javascript alert. In case the value is "no" empty comment message
will be displayed inside HTML element whose id attribute is the value of "comments_error_message_id" parameter.

26) pagination_symbol - optional. Allows you to specify a letter or word which in URL indicates pagination. Default value is "N".
You will need to set some other value (most probably "P") in case in comment template you use some other ExpressionEngine tag 
instead of exp:comment:entries (e.g. for outputting comments you can use exp:query tag). In such case you will need to use the tag
exp:ajax_babble:pagination which also has parameter "pagination_symbol".

27) protect_backslashes - Optional. In some cases parsing of an ExpressionEngine's teplate results into removal of
the backslashes inside of some tags. This is probably the case if adding exp:ajax_babble:script tag into template results into javascript errors.
This parameter can have the value "yes" or "no" (default).

II. THE TAG exp:ajax_babble:comments

This is a *tag pair* used to *wrap* the tag exp:comment:entries.

PARAMETERS

1) entry_id - required. This parameter *must* have the value {embed:entry_id}

2) limit - optional. Allows you to specify how many comments should be displayed on one page. Default is 100.
This parameter *must* have exactly the same value as "limit" parameter of exp:comment:entries tag which
is wrapped by exp:ajax_babble:comments tag.

3) max_pagination_links - optional. Allows you to specify how many pagination links should be 
  displayed. Default value is "2".

4) sort - optional. Allows you to specify how - ascendingly (asc)  or descendingly (desc) - comment entries
should be sorted. Default value is "desc". This parameter *must* have exactly the same value as "sort" parameter 
of exp:comment:entries tag which is wrapped by exp:ajax_babble:comments tag.

5) orderby - optional. This parameter sets the display order of the comments. Possible values are: date, email,
location, name, url. Default id "date". This parameter *must* have exactly the same value as "orderby" parameter 
of exp:comment:entries tag which is wrapped by exp:ajax_babble:comments tag.

5) status - optional. Allows to specify status of comments. Defaul value is "open"
E.g. status="open|closed" 

6) search_trigger - optional. Allows you to specify the string in url which will trigger the search for
certain comment. E.g. if this parameter has the value "comment_", then in case some segment is, say, "comment_255", 
the search will be triggered for the comment having ID number 255.

7) css_id_to_scroll - optional. Allows you to specify CSS id parameter of the comment which will
be scrolled into view after AJAX will load comments. Into view will be scrolled either 
the newly submitted comment or the comment which was found by the search triggered by URL.
The value of this parameter *must* be the same as the value of 
identically named parameter of exp:ajax_babble:script tag.

8) parse - required. This parameter *must* have the value "inward".

9) parse_pagination_links - optional. Allows you to specify if the tag exp:ajax_babble:comments should parse
single variable {pagination_links}. Default value is "yes". Set the value to "no" in following cases: (a) for flexible pagination you use 
variable pair {pagination_links}{/pagination_links} instead of single variable {pagination_links} 
(see http://expressionengine.com/user_guide/modules/channel/pagination_page.html), (b) in comments template you use
some other ExpressionEngine tag instead of exp:comment:entries (e.g. you can output comments using exp:query tag).
In case you set the value of this parameter to "no" you should use tag pair exp:ajax_babble:pagination (see below).

VARIABLES

1) ajax_babble_entry_id - outputs entry_id number of the entry comments belong to. Used as the value of
of exp:comment:entries tag which is wrapped by exp:ajax_babble:comments tag.

2) ajax_babble_comment_id_to_scroll - comment id of the newly submitted comment or comment id of the
comment the search was triggered for by the string in URL. Used inside conditional which outputs
CSS ID parameter specified by "css_id_to_scroll" parameter of exp:ajax_babble:script tag.

3) ajax_babble_url_title - outputs url_title of the entry comments belong to.

4) ajax_babble_weblog_name (for EE1.x) - outputs short weblog name of the weblog entry being commented belongs to.

5) ajax_babble_channel_name (for EE2.0 plus) - outputs short channel name of the weblog entry being commented belongs to.

6) ajax_babble_segment_1, ajax_babble_segment_2, ajax_babble_segment_3, ajax_babble_segment_4, ajax_babble_segment_5,
ajax_babble_segment_6, ajax_babble_segment_7, ajax_babble_segment_8, ajax_babble_segment_9 - outputs segments of the URL
which was used to output *main* template.

7) ajax_babble_search_trigger_segment - outputs URL segment in which search triggering string is present.

8) ajax_babble_css_id_to_scroll - outputs the value of "css_id_to_scroll" parameter.

III. MAIN TEMPLATE (E.G technical/ajax_babble_main)

<html>
<head>

<title>AJAX Babble demo</title>

<style>
.pagination a {
cursor: pointer;
color:blue;
}

.pagination a:hover {
text-decoration:underline;
}

div.progress_indicator {
border: solid 1px green;
background-image: url({site_url}images/ajax-loader.gif);
background-repeat: no-repeat;
background-position: center;
height: 7em;
width: 35em;
text-align: center;
display: block!important;
}

div.indicator  {
display: none;
}

.ajax_error {
border: solid 1px red;
height: 7em;
width: 35em;
display: block!important;
}

ul {
width: 20em;
list-style-type: none;
margin: 0;
padding: 0;
}

li {
border: dotted 1px #ccc;
padding: 0.5em;
margin-bottom: 0.5em;
}

blockquote {
margin: 0em;
padding: 0em;
}

ul div {
position:relative;
}

ul div span {
position: absolute;
right: 0;
}
</style>

</head>

<body>

<h1>AJAX Babble demo</h1>

<p>
This plugin uses AJAX to submit comments. Supports pagination, scrolling newly submitted comment into view and triggering search for certain comment through URL. To trigger search for certain comment right-click on the link in the comment's top right corner.
</p>

<div id="preview_progress_indicator" class="indicator">
Loading comment preview...
</div>

<div id="preview_error_message" class="indicator">
<h3 style="margin-bottom: 1em; color: #cc3300;">Error occurred:</h3>
</div>

<div id="preview_container">

</div>

{exp:comment:form entry_id="3665"}

<h2>Post a Comment</h2>

<p><b>Notice.</b> Please, post something sensible. Otherwise computer will think your comment is a spam.</p>

{if logged_out}
<p>Name: <input type="text" name="name" value="" size="50" /></p>

<p>Email: <input type="text" name="email" value="" size="50" /></p>

<p>URL: <input type="text" name="url" value="" size="50" /></p>
{/if}

<textarea name="comment" cols="50" rows="12"></textarea>

<p><input type="submit" id="comments_submit_button" name="submit" value="Submit" />
<input type="submit" id="comments_preview_button" name="preview" value="Preview" /></p>

{/exp:comment:form}

{exp:ajax_babble:script form_id="comment_form" entry_id="3665" comments_template_url="{homepage}/technical/ajax_babble_comments/" preview_template_url="{homepage}/technical/ajax_babble_preview/" comments_container_id="comments_container" preview_container_id="preview_container" submit_button_id="comments_submit_button" preview_button_id="comments_preview_button"  comments_number_id="comments_number" css_id_to_scroll="comment_to_scroll" comments_progress_indicator_id="comments_progress_indicator" preview_progress_indicator_id="preview_progress_indicator" progress_indicator_class="progress_indicator" comments_error_message_id="comments_error_message" preview_error_message_id="preview_error_message" error_message_class="ajax_error" empty_comment_message_text="Comment cannot be empty!" empty_comment_display_js_alert="yes"}

<div id="comments_progress_indicator" class="indicator">
Loading comments...
</div>

<div id="comments_error_message" class="indicator">
<h3 style="margin-bottom: 1em; color: #cc3300;">Error occurred:</h3>
</div>

<div id="comments_container">
{embed="technical/ajax_babble_comments" entry_id="3665"}
</div>

</body>
</html>  

IV. COMMENTS TEMPLATE (E.G technical/ajax_babble_comments)

<h2>Comments:</h2>

<ul>

{exp:ajax_babble:comments entry_id="{embed:entry_id}" limit="5" search_trigger="comment_" orderby="date" sort="asc" parse="inward"}

{exp:comment:entries entry_id="{ajax_babble_entry_id}" limit="5" orderby="date" sort="asc" paginate="top" dynamic="off"}

{paginate}
<p class="pagination">Page {current_page} of {total_pages} pages {pagination_links}</p>
{/paginate}

<li {if "{ajax_babble_comment_id_to_scroll}"=="{comment_id}"}id="comment_to_scroll"{/if} class="comment-body">
<div>{url_as_author} at {comment_date format='%m/%d - %h:%i %A'} said: <span><a href="{homepage}/{ajax_babble_segment_1}/{if "{ajax_babble_segment_2}" != "" AND "{ajax_babble_segment_2}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_2}/{/if}{if "{ajax_babble_segment_3}" != "" AND "{ajax_babble_segment_3}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_3}/{/if}{if "{ajax_babble_segment_4}" != "" AND "{ajax_babble_segment_4}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_4}/{/if}{if "{ajax_babble_segment_5}" != "" AND "{ajax_babble_segment_5}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_5}/{/if}comment_{comment_id}/">{absolute_count}</a></span></div>
<blockquote>{comment}</blockquote>
</li>

{/exp:comment:entries}

{/exp:ajax_babble:comments}

</ul>

V. PREVIEW TEMPLATE (E.G technical/ajax_babble_preview)

<h2>Comment preview:</h2>

{exp:comment:preview}

<ul>

<li>

<div>{name} at {comment_date format='%m/%d - %h:%i %A'} said:</div>

<blockquote>{comment}</blockquote>

</li>

</ul>

{/exp:comment:preview}

VI. DELETING COMMENTS

If you want to allow logged-in users to delete comments on the frontent, it is possible using AJAX Babble. 
(Members of Superadmins group will be able to delete any comment, members of the groups with option 
"Can delete comments in their own weblog entries" on will be able delete any comment on the entry they authored,
and other logged-in members will be able to delete their own comments only.)

Adding comments deletion functionality involves two steps.

First, you should create a new template, e.g. technical/ajax_babble_delete_comment. In this template you should place 
only the tag exp:ajax_babble:delete_comment. This tag can have following parameters:

1) no_permission_msg - optional. This is the text of error message to display when user don't have permisison to delete comment.
Default value of this parameter is "You're not permitted to delete this comment.".

2) comment_not_found_msg - optional. This is the text of error message to display when some comment doesn't exist or has been deleted.
Default value of this parameter is "The comment does not exist or has been already deleted.".

Example of technical/ajax_babble_delete_comment template:

{exp:ajax_babble:delete_comment}

Second, you should add exp:ajax_babble:delete_link tag to technical/ajax_babble_comments template. This is *single* tag
which should be used *inside* exp:comment:entries tag pair. exp:ajax_babble:delete_link can have following parameters:

1) comment_id - required. ID number of the comment to be deleted.

2) comment_html_id - required. HTML "id" attribute of the element which contains comment to be deleted.

3) entry_id - required. ID number of the entry comment belongs to.

4) delete_template_url - required. Allows you to specify URL of the template you placed exp:ajax_babble:delete_comment tag.
You must specify full URL, i.e. starting with "http". This parameter accepts inside its value
the following ExpressionEngine variables: site_id, site_url, site_index, homepage.

5) class - optional. HTML "class" attribute of the delete comment link.

6) link_text - optional. The text of delete comment link. Default value is "Delete".

7) alert_text - optional. The text that will be displayed by javascript confirm dialogue
after delete comment link has been clicked. Default value is "Do you really want to delete this comment?".

8) limit - optional. Allows you to specify how many comments should be displayed on one page. Default is 100.
This parameter *must* have exactly the same value as "limit" parameter of exp:comment:entries tag.

9) sort - optional. Allows you to specify how - ascendingly (asc)  or descendingly (desc) - comment entries
should be sorted. Default value is "desc". This parameter *must* have exactly the same value as "sort" parameter 
of exp:comment:entries tag.

10) orderby - optional. This parameter sets the display order of the comments. Possible values are: date, email,
location, name, url. Default id "date". This parameter *must* have exactly the same value as "orderby" parameter 
of exp:comment:entries tag.

11) status - optional. Allows to specify status of comments. Defaul value is "open"
E.g. status="open|closed"

Example of technical/ajax_babble_comments with exp:ajax_babble:delete_link tag:

<ul>

{exp:ajax_babble:comments entry_id="{embed:entry_id}" limit="5" search_trigger="comment_" orderby="date" sort="asc" parse="inward"}

{exp:comment:entries entry_id="{ajax_babble_entry_id}" limit="5" orderby="date" sort="asc" paginate="top" dynamic="off"}

{paginate}
<p class="pagination">Page {current_page} of {total_pages} pages {pagination_links}</p>
{/paginate}

<li id="comment_wrapper_id_{comment_id}" class="comment-body">
<div{if "{ajax_babble_comment_id_to_scroll}"=="{comment_id}"} id="comment_to_scroll"{/if}>{url_as_author} at {comment_date format='%m/%d - %h:%i %A'} said: <span><a href="{homepage}/{ajax_babble_segment_1}/{if "{ajax_babble_segment_2}" != "" AND "{ajax_babble_segment_2}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_2}/{/if}{if "{ajax_babble_segment_3}" != "" AND "{ajax_babble_segment_3}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_3}/{/if}{if "{ajax_babble_segment_4}" != "" AND "{ajax_babble_segment_4}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_4}/{/if}{if "{ajax_babble_segment_5}" != "" AND "{ajax_babble_segment_5}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_5}/{/if}comment_{comment_id}/">{absolute_count}</a></span></div>
<blockquote>{comment}</blockquote>

{exp:ajax_babble:delete_link comment_id="{comment_id}" comment_html_id="comment_wrapper_id_{comment_id}" entry_id="{ajax_babble_entry_id}" delete_template_url="{homepage}/technical/embed_ajax_babble_delete_comment/" class="delete_link" limit="5" orderby="date" sort="asc"}

</li>

{/exp:comment:entries}

{/exp:ajax_babble:comments}

</ul>

VII. THE TAG exp:ajax_babble:pagination and flexible pagination

This is a *tag pair* used to *wrap* pagination links. This tag pair is needed in following cases: (a) for flexible pagination you use 
variable pair {pagination_links}{/pagination_links} instead of single variable {pagination_links} 
(see http://expressionengine.com/user_guide/modules/channel/pagination_page.html), (b) in comments template you use
some other ExpressionEngine tag instead of exp:comment:entries (e.g. you can output comments using exp:query tag).
The tag exp:ajax_babble:pagination has following parameters:

1) entry_id - required. Allows you to specify ID number of the entry being commented on.

2) pagination_symbol - optional. Allows you to specify a letter or word which in URL indicates pagination. Default value is "N".
You will need to set some other value (most probably "P") in case in comment template you use some other ExpressionEngine tag 
instead of exp:comment:entries (e.g. for outputting comments you can use exp:query tag). NOTICE: the tag exp:ajax_babble:script
also has paramater "pagination_symbol" which must have the same value as paramater "pagination_symbol" of exp:ajax_babble:pagination tag.

Let's say you use variable pair {pagination_links}{/pagination_links} instead of single variable {pagination_links} for flexible pagination.
Then in your comments template you should use the tag exp:ajax_babble:pagination and add parse_pagination_links="no" parameter to
exp:ajax_babble:comments tag:

<h2>Comments:</h2>

<ul>

{exp:ajax_babble:comments entry_id="{embed:entry_id}" limit="5" search_trigger="comment_" orderby="date" sort="asc" parse_pagination_links="no" parse="inward"}

{exp:comment:entries entry_id="{ajax_babble_entry_id}" limit="5" orderby="date" sort="asc" paginate="top" dynamic="no" parse="inward"}

{paginate}
<p class="pagination">Page {current_page} of {total_pages} pages
 
{exp:ajax_babble:pagination entry_id="{ajax_babble_entry_id}"}

{pagination_links}

{first_page}
<a href="{pagination_url}" class="page-first">First Page</a>
{/first_page}

{previous_page}
<a href="{pagination_url}" class="page-previous">Previous Page</a>
{/previous_page}

{page}
<a href="{pagination_url}" class="page-{pagination_page_number} {if current_page}active{/if}">{pagination_page_number}</a>
{/page}

{next_page}
<a href="{pagination_url}" class="page-next">Next Page</a>
{/next_page}

{last_page}
<a href="{pagination_url}" class="page-last">Last Page</a>
{/last_page}

{/pagination_links}

{/exp:ajax_babble:pagination}

</p>
{/paginate}

<li {if "{ajax_babble_comment_id_to_scroll}"=="{comment_id}"}id="comment_to_scroll"{/if} class="comment-body">
<div>{url_as_author} at {comment_date format='%m/%d - %h:%i %A'} said: <span><a href="{homepage}/{ajax_babble_segment_1}/{if "{ajax_babble_segment_2}" != "" AND "{ajax_babble_segment_2}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_2}/{/if}{if "{ajax_babble_segment_3}" != "" AND "{ajax_babble_segment_3}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_3}/{/if}{if "{ajax_babble_segment_4}" != "" AND "{ajax_babble_segment_4}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_4}/{/if}{if "{ajax_babble_segment_5}" != "" AND "{ajax_babble_segment_5}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_5}/{/if}comment_{comment_id}/">{absolute_count}</a></span></div>
<blockquote>{comment}</blockquote>
</li>

{/exp:comment:entries}

{/exp:ajax_babble:comments}

</ul>

VIII. USAGE WITH RATING MODULE LIKEE

To use AJAX Babble with<a href="http://devot-ee.com/add-ons/likee/"> rating module Likee</a> 
you need to add Likee's tags not into main, but into embedded template 
(in AJAX Babble's description it is technical/ajax_babble_comments template):

{exp:likee:js}

<h2>Comments:</h2>

<ul>

{exp:ajax_babble:comments entry_id="{embed:entry_id}" limit="5" search_trigger="comment_" orderby="date" sort="asc" parse="inward"}

{exp:comment:entries entry_id="{ajax_babble_entry_id}" limit="5" orderby="date" sort="asc" paginate="top" dynamic="off"}

{paginate}
<p class="pagination">Page {current_page} of {total_pages} pages {pagination_links}</p>
{/paginate}

<li {if "{ajax_babble_comment_id_to_scroll}"=="{comment_id}"}id="comment_to_scroll"{/if} class="comment-body">
<div>{url_as_author} at {comment_date format='%m/%d - %h:%i %A'} said: <span><a href="{homepage}/{ajax_babble_segment_1}/{if "{ajax_babble_segment_2}" != "" AND "{ajax_babble_segment_2}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_2}/{/if}{if "{ajax_babble_segment_3}" != "" AND "{ajax_babble_segment_3}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_3}/{/if}{if "{ajax_babble_segment_4}" != "" AND "{ajax_babble_segment_4}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_4}/{/if}{if "{ajax_babble_segment_5}" != "" AND "{ajax_babble_segment_5}" != "{ajax_babble_search_trigger_segment}"}{ajax_babble_segment_5}/{/if}comment_{comment_id}/">{absolute_count}</a></span></div>
<blockquote>

{comment}

{exp:likee entry_id="{comment_id}"}
   I {like} it.
   I {dislike} it.
   {like_count} likes
   {dislike_count} dislikes    
{/exp:likee}

</blockquote>
</li>

{/exp:comment:entries}

{/exp:ajax_babble:comments}

</ul>

<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
// END FUNCTION

function _comments_number($entry_id, $status)
{  
  if (!$status)
  {
    $status_clause = " status != 'c' ";
  }
  else
  {
    $status_array = explode('|', $status);
    foreach ($status_array as $key => $val)
    {
      $status_array[$key] = substr($val, 0, 1);
    }
    $status_clause = " status IN ('".implode("','", $status_array)."') ";
  }
  
  $sql_comments_number = "SELECT comment_id
                          FROM exp_comments
                          WHERE entry_id ='".$entry_id."' AND ".$status_clause;
  $query_comments_number = $this->EE->db->query($sql_comments_number);
  $comments_number = $query_comments_number->num_rows();
  
  return $comments_number;
}
// END FUNCTION 

function _pagination_links($comments_number, $limit, $pagination_number, $max_pagination_links, $entry_id)
{
  $this->EE->load->library('pagination');
  
  $p_config = array();
  $p_config['total_rows'] = $comments_number;
  $p_config['per_page'] = $limit;
  $p_config['cur_page'] = $pagination_number;
  $p_config['num_links'] = $max_pagination_links;
  $p_config['first_url'] = '';
  $p_config['query_string_segment'] = 'N';
  $p_config['page_query_string'] = FALSE;
  $p_config['base_url'] = '';
  $p_config['uri_segment'] = 4;
  
  $this->EE->pagination->initialize($p_config);
  $pagination_links = $this->EE->pagination->create_links();
  
  $pagination_links = str_replace('"/"', '"0"', $pagination_links);
  $pagination_links = str_replace('"/', '"', $pagination_links);
  $pattern = '/href=\"(.*)\"/Usi';
  while (preg_match($pattern, $pagination_links, $matches))
  {
    $onclick_param = 'onclick="ajaxBabbleEntry'.$entry_id.'.fetchComments(\''.@$matches[1].'\', false, true);"';
    $pagination_links = preg_replace($pattern, $onclick_param, $pagination_links, 1);
  }
  
  return $pagination_links;
}
// END FUNCTION

function _template_url($template_url)
{  
  $site_id = $this->EE->config->item('site_id');
  
  $site_url = stripslashes($this->EE->config->item('site_url'));
  $site_url = html_entity_decode($site_url);
  $last_symbol = substr($site_url, strlen($site_url) - 1);
  if ($last_symbol !== '/')
  {
    $site_url .= '/';
  }
  
  $site_index = stripslashes($this->EE->config->item('site_index'));
  
  $homepage = $site_url.$site_index;
  
  $template_url = str_replace(LD.'site_id'.RD, $site_id, $template_url);
  $template_url = str_replace(LD.'site_url'.RD, $site_url, $template_url);
  $template_url = str_replace(LD.'site_index'.RD, $site_index, $template_url);
  $template_url = str_replace(LD.'homepage'.RD, $homepage, $template_url);
  $template_url = html_entity_decode($template_url);
  $last_symbol = substr($template_url, strlen($template_url) - 1);
  if ($last_symbol !== '/')
  {
    $template_url .= '/';
  }

  return $template_url;
}
// END FUNCTION

function _find_comment_id_in_url($search_trigger)
{  
  $comment_id = FALSE;
  
  $url_segments = $this->EE->uri->segments;
  for ($i = 1; $i <= count($url_segments); $i++)
  {
    if (strpos($url_segments[$i], $search_trigger) === 0)
    {
      $search_trigger_length = strlen($search_trigger);
      $comment_id = substr($url_segments[$i], $search_trigger_length);
      
      return $comment_id;
    }
  }
}
// END FUNCTION

function _find_comment($comment_id, $entry_id, $orderby, $sort, $limit)
{ 
  $pagination_number = 0;
      
  if (!is_numeric($comment_id))
  {
    return 0;
  }
  
  if ($orderby == 'date')
  {
    $orderby_clause = ' exp_comments.comment_date ';
  }
  else
  {
    $orderby_clause = ' exp_comments.'.$orderby.' ';
  }
  
  //Find total number of comments
  $sql_comments_number = "SELECT
                            exp_comments.comment_id, exp_comments.comment
                          FROM
                            exp_comments
                          WHERE
                            exp_comments.entry_id = '".$entry_id."' AND exp_comments.status = 'o'
                          ORDER BY ".$orderby_clause.' '.strtoupper($sort);
  $query_comments_number = $this->EE->db->query($sql_comments_number);
  $total_comments = $query_comments_number->num_rows();
  $query_comments_number_result = $query_comments_number->result_array();
  
  if ($total_comments > 0)
  {
    // Find absolute count number of certain comment
    for ($i = 0; $i < $total_comments; $i++)
    {
      if ($query_comments_number_result[$i]['comment_id'] == $comment_id)
      {
        $absolute_count = $i + 1;

        $needed_page = ceil($absolute_count / $limit);

  		    $pagination_number = ($needed_page - 1) * $limit;
  		    
  		    break;
      }
    }
  }
  
  return $pagination_number;
}
// END FUNCTION

}
// END CLASS

?>
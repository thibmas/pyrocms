<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Entries Driver
 *
 * @author  	Parse19
 * @package  	PyroCMS\Core\Libraries\Streams\Drivers
 */ 
 
class Streams_entries extends CI_Driver {

	/**
	 * Available entry parameters
	 * and their defaults.
	 *
	 * @access	public
	 * @var		array
	 */
	public $entries_params = array(
			'stream'			=> null,
			'namespace'			=> null,
			'limit'				=> null,
			'offset'			=> 0,
			'single'			=> 'no',
			'id'				=> null,
			'date_by'			=> 'created',
			'year'				=> null,
			'month'				=> null,
			'day'				=> null,
			'show_upcoming'		=> 'yes',
			'show_past'			=> 'yes',
			'restrict_user'		=> 'no',
			'where'				=> null,
			'exclude'			=> null,
			'exclude_by'		=> 'id',
			'include'			=> null,
			'include_by'		=> 'id',
			'disable'			=> null,
			'order_by'			=> 'created',
			'sort'				=> 'desc',
			'exclude_called'	=> 'no',
			'paginate'			=> 'no',
			'pag_segment'		=> 2,
			'partial'			=> null,
			'site_ref'			=> SITE_REF
	);

	// --------------------------------------------------------------------------

	/**
	 * Pagination Config
	 *
	 * These are the CI defaults that can be
	 * overridden by PyroStreams.
	 *
	 * @access	public
	 * @var		array
	 */
	public $pagination_config = array(
			'num_links'		=> 3,
			'full_tag_open'		=> '<p>',
			'full_tag_close'	=> '</p>',
			'first_link'		=> 'First',
			'first_tag_open'	=> '<div>',
			'first_tag_close'	=> '</div>',
			'last_link'		=> 'Last',
			'last_tag_open'		=> '<div>',
			'last_tag_close'	=> '</div>',
			'next_link'		=> '&gt;',
			'next_tag_open'		=> '<div>',
			'next_tag_close'	=> '</div>',
			'prev_link'		=> '&lt;',
			'prev_tag_open'		=> '<div>',
			'prev_tag_close'	=> '</div>',
			'cur_tag_open'		=> '<span>',
			'cur_tag_close'		=> '</span>',
			'num_tag_open'		=> '<div>',
			'num_tag_close'		=> '</div>',
			'display_pages'		=> true
	);

	// --------------------------------------------------------------------------

	/**
	 * Get entries for a stream.
	 *
	 * @access	public
	 * @param	array - parameters
	 * @param	[array - pagination config]
	 * @param	[bool - should we not do param defaults? Use with caution.]
	 * @return	array
	 */
	public function get_entries($params, $pagination_config = array(), $skip_params = false)
	{
		$return = array();

		$CI = get_instance();
		
		// -------------------------------------
		// Set Parameters
		// -------------------------------------

		if ( ! $skip_params)
		{
			foreach ($this->entries_params as $param => $default)
			{
				if ( ! isset($params[$param]) and ! is_null($this->entries_params[$param])) $params[$param] = $default;
			}
		}
	
		// -------------------------------------
		// Stream Data Check
		// -------------------------------------
		
		if ( ! isset($params['stream'])) $this->log_error('no_stream_provided', 'get_entries');
				
		if ( ! isset($params['namespace'])) $this->log_error('no_namespace_provided', 'get_entries');
	
		$stream = $CI->streams_m->get_stream($params['stream'], true, $params['namespace']);
				
		if ( ! $stream) $this->log_error('invalid_stream', 'get_entries');

		// -------------------------------------
		// Pagination Limit
		// -------------------------------------

		if ($params['paginate'] == 'yes' and ( ! isset($params['limit']) or ! is_numeric($params['limit']))) $params['limit'] = 25;

		// -------------------------------------
		// Get Rows
		// -------------------------------------

		$rows = $CI->row_m->get_rows($params, null, $stream);
		
		$return['entries'] = $rows['rows'];
				
		// -------------------------------------
		// Pagination
		// -------------------------------------
		
		if ($params['paginate'] == 'yes')
		{
			$return['total'] 	= $rows['pag_count'];
			
			// Add in our pagination config
			// override varaibles.
			foreach ($this->pagination_config as $key => $var)
			{
				if (isset($pagination_config[$key]))
				{
					$this->pagination_config[$key] = $pagination_config[$key];
				}

				// Make sure we set the false params to boolean
				if ($this->pagination_config[$key] === 'false')
				{
					$this->pagination_config[$key] = false;
				}
			}
			
			$return['pagination'] = $CI->row_m->build_pagination($params['pag_segment'], $params['limit'], $return['total'], $this->pagination_config);
		}		
		else
		{
			$return['pagination'] 	= null;
			$return['total'] 		= count($return['entries']);
		}

		// -------------------------------------
	
		return $return;
	}

	// --------------------------------------------------------------------------

	/**
	 * Get a single entry
	 *
	 * @access	public
	 * @param	int - entry id
	 * @param	stream - int, slug, or obj
	 * @param	bool - format results?
	 * @return	object
	 */
	public function get_entry($entry_id, $stream, $namespace, $format = true, $plugin_call = true)
	{
		return get_instance()->row_m->get_row($entry_id, $this->stream_obj($stream, $namespace), $format, $plugin_call);
	}

	// --------------------------------------------------------------------------

	/**
	 * Delete an entry
	 *
	 * @access	public
	 * @param	int - entry id
	 * @param	stream - int, slug, or obj
	 * @return	object
	 */
	public function delete_entry($entry_id, $stream, $namespace)
	{
		return get_instance()->row_m->delete_row($entry_id, $this->stream_obj($stream, $namespace));
	}

	// --------------------------------------------------------------------------

	/**
	 * Insert an entry
	 *
	 * This will be run through the streams data
	 * processing.
	 *
	 * @access	public
	 * @param	array - entry data
	 * @param	stream - int, slug, or obj
	 * @param 	string - namespace
	 * @param 	array - field slugs to skip
	 * @param 	array - extra data to add in
	 * @return	object
	 */
	public function insert_entry($entry_data, $stream, $namespace, $skips = array(), $extra = array())
	{
		$str_obj = $this->stream_obj($stream, $namespace);
		
		if ( ! $str_obj) $this->log_error('invalid_stream', 'insert_entry');

		$CI = get_instance();

		$stream_fields = $CI->streams_m->get_stream_fields($str_obj->id);

		return $CI->row_m->insert_entry($entry_data, $stream_fields, $str_obj, $skips, $extra);
	}

	// --------------------------------------------------------------------------

	/**
	 * Update an entry
	 *
	 * @param	int - entry id
	 * @param	array - entry data
	 * @param	stream - int, slug, or obj
	 * @param 	string - namespace
	 * @param 	array - field slugs to skip
	 * @param 	array - assoc array of extra data to add
	 * @param 	bool - update only the passed values?
	 * @return	object
	 */
	public function update_entry($entry_id, $entry_data, $stream, $namespace, $skips = array(), $extra = array(), $include_only_passed = false)
	{
		$str_obj = $this->stream_obj($stream, $namespace);
		
		if ( ! $str_obj) $this->log_error('invalid_stream', 'update_entry');

		$CI = get_instance();

		$stream_fields = $CI->streams_m->get_stream_fields($str_obj->id);

		return $CI->row_m->update_entry($stream_fields, $str_obj, $entry_id, $entry_data, $skips, $extra, $include_only_passed);
	}
	
}

<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    testing_platform
 */

/**
 * Composr test case class (unit testing).
 */
class phpstub_accuracy_test_set extends cms_test_case
{
    public function testFunctionsNeeded()
    {
        $c = file_get_contents(get_file_base() . '/sources_custom/phpstub.php');
        $matches = array();
        $num_matches = preg_match_all('#^function (\w+)\(#m', $c, $matches);
        $declared_functions = array();
        for ($i = 0; $i < $num_matches; $i++) {
            $function = $matches[1][$i];
            $declared_functions[] = $function;
        }
        sort($declared_functions);

        $c = file_get_contents(get_file_base() . '/sources/hooks/systems/checks/functions_needed.php');
        $num_matches = preg_match_all('#<<<END(.*)END;#Us', $c, $matches);
        $c = '';
        for ($i = 0; $i < $num_matches; $i++) {
            $c .= $matches[1][$i] . "\n";
        }
        $c = str_replace("\n", ' ', $c);
        $c = trim(preg_replace('#\s+#', ' ', $c));
        $required_functions = explode(' ', $c);
        sort($required_functions);

        foreach ($declared_functions as $function) {
            $this->assertTrue(in_array($function, $required_functions), 'Missing from functions_needed.php? ' . $function);
        }

        foreach ($required_functions as $function) {
            $this->assertTrue(in_array($function, $declared_functions), 'Missing from phpstub.php? ' . $function);
        }

        if (get_param_integer('dev_check', 0) == 1) { // This extra switch let's us automatically find new functions in PHP we aren't coding for
            $will_never_define = array(
                'zend_version',
                'property_exists',
                'interface_exists',
                'get_required_files',
                'user_error',
                'restore_exception_handler',
                'get_declared_interfaces',
                'get_loaded_extensions',
                'extension_loaded',
                'get_extension_funcs',
                'get_defined_constants',
                'zip_open',
                'zip_close',
                'zip_read',
                'zip_entry_open',
                'zip_entry_close',
                'zip_entry_read',
                'zip_entry_filesize',
                'zip_entry_name',
                'zip_entry_compressedsize',
                'zip_entry_compressionmethod',
                'xmlwriter_open_uri',
                'xmlwriter_open_memory',
                'xmlwriter_set_indent',
                'xmlwriter_set_indent_string',
                'xmlwriter_start_comment',
                'xmlwriter_end_comment',
                'xmlwriter_start_attribute',
                'xmlwriter_end_attribute',
                'xmlwriter_write_attribute',
                'xmlwriter_start_attribute_ns',
                'xmlwriter_write_attribute_ns',
                'xmlwriter_start_element',
                'xmlwriter_end_element',
                'xmlwriter_full_end_element',
                'xmlwriter_start_element_ns',
                'xmlwriter_write_element',
                'xmlwriter_write_element_ns',
                'xmlwriter_start_pi',
                'xmlwriter_end_pi',
                'xmlwriter_write_pi',
                'xmlwriter_start_cdata',
                'xmlwriter_end_cdata',
                'xmlwriter_write_cdata',
                'xmlwriter_text',
                'xmlwriter_write_raw',
                'xmlwriter_start_document',
                'xmlwriter_end_document',
                'xmlwriter_write_comment',
                'xmlwriter_start_dtd',
                'xmlwriter_end_dtd',
                'xmlwriter_write_dtd',
                'xmlwriter_start_dtd_element',
                'xmlwriter_end_dtd_element',
                'xmlwriter_write_dtd_element',
                'xmlwriter_start_dtd_attlist',
                'xmlwriter_end_dtd_attlist',
                'xmlwriter_write_dtd_attlist',
                'xmlwriter_start_dtd_entity',
                'xmlwriter_end_dtd_entity',
                'xmlwriter_write_dtd_entity',
                'xmlwriter_output_memory',
                'xmlwriter_flush',
                'libxml_set_streams_context',
                'libxml_use_internal_errors',
                'libxml_get_last_error',
                'libxml_clear_errors',
                'libxml_get_errors',
                'dom_import_simplexml',
                'xml_parser_create',
                'xml_parser_create_ns',
                'xml_set_object',
                'xml_set_element_handler',
                'xml_set_character_data_handler',
                'xml_set_processing_instruction_handler',
                'xml_set_default_handler',
                'xml_set_unparsed_entity_decl_handler',
                'xml_set_notation_decl_handler',
                'xml_set_external_entity_ref_handler',
                'xml_set_start_namespace_decl_handler',
                'xml_set_end_namespace_decl_handler',
                'xml_parse',
                'xml_parse_into_struct',
                'xml_get_error_code',
                'xml_error_string',
                'xml_get_current_line_number',
                'xml_get_current_column_number',
                'xml_get_current_byte_index',
                'xml_parser_free',
                'xml_parser_set_option',
                'xml_parser_get_option',
                'token_get_all',
                'token_name',
                'time_nanosleep',
                'time_sleep_until',
                'strptime',
                'htmlspecialchars_decode',
                'sha1_file',
                'iptcparse',
                'iptcembed',
                'phpinfo',
                'phpversion',
                'phpcredits',
                'php_logo_guid',
                'php_real_logo_guid',
                'php_egg_logo_guid',
                'zend_logo_guid',
                'php_sapi_name',
                'php_ini_scanned_files',
                'php_ini_loaded_file',
                'strripos',
                'nl_langinfo',
                'chop',
                'strchr',
                'vfprintf',
                'sscanf',
                'readlink',
                'linkinfo',
                'symlink',
                'link',
                'exec',
                'system',
                'escapeshellcmd',
                'escapeshellarg',
                'passthru',
                'shell_exec',
                'proc_open',
                'proc_close',
                'proc_terminate',
                'proc_get_status',
                'proc_nice',
                'rand',
                'getservbyname',
                'getservbyport',
                'getprotobyname',
                'getprotobynumber',
                'getmyuid',
                'getmygid',
                'getmypid',
                'getmyinode',
                'convert_uuencode',
                'convert_uudecode',
                'asinh',
                'acosh',
                'atanh',
                'expm1',
                'log1p',
                'fmod',
                'getopt',
                'sys_getloadavg',
                'getrusage',
                'get_current_user',
                'set_time_limit',
                'magic_quotes_runtime',
                'set_magic_quotes_runtime',
                'import_request_variables',
                'error_get_last',
                'call_user_method',
                'call_user_method_array',
                'debug_zval_dump',
                'memory_get_peak_usage',
                'register_tick_function',
                'unregister_tick_function',
                'highlight_file',
                'show_source',
                'highlight_string',
                'php_strip_whitespace',
                'ini_get_all',
                'ini_alter',
                'get_include_path',
                'set_include_path',
                'restore_include_path',
                'setrawcookie',
                'dns_check_record',
                'checkdnsrr',
                'dns_get_mx',
                'getmxrr',
                'doubleval',
                'settype',
                'is_long',
                'is_int',
                'is_double',
                'is_real',
                'ocp_mark_as_escaped',
                'ocp_is_escaped',
                'ereg',
                'ereg_replace',
                'eregi',
                'eregi_replace',
                'split',
                'spliti',
                'join',
                'sql_regcase',
                'dl',
                'pclose',
                'popen',
                'umask',
                'fputs',
                'tempnam',
                'tmpfile',
                'stream_select',
                'stream_context_set_params',
                'stream_context_set_option',
                'stream_context_get_options',
                'stream_context_get_default',
                'stream_filter_prepend',
                'stream_filter_append',
                'stream_filter_remove',
                'stream_socket_client',
                'stream_socket_server',
                'stream_socket_accept',
                'stream_socket_get_name',
                'stream_socket_recvfrom',
                'stream_socket_sendto',
                'stream_socket_enable_crypto',
                'stream_socket_shutdown',
                'stream_socket_pair',
                'stream_copy_to_stream',
                'stream_get_contents',
                'stream_set_write_buffer',
                'set_file_buffer',
                'set_socket_blocking',
                'stream_set_blocking',
                'socket_set_blocking',
                'stream_get_meta_data',
                'stream_get_line',
                'stream_wrapper_register',
                'stream_register_wrapper',
                'stream_wrapper_unregister',
                'stream_wrapper_restore',
                'stream_get_wrappers',
                'stream_get_transports',
                'stream_is_local',
                'stream_set_timeout',
                'socket_set_timeout',
                'socket_get_status',
                'fnmatch',
                'fsockopen',
                'pfsockopen',
                'get_browser',
                'dir',
                'is_writeable',
                'lstat',
                'chown',
                'chgrp',
                'lchown',
                'lchgrp',
                'disk_total_space',
                'disk_free_space',
                'diskfreespace',
                'openlog',
                'syslog',
                'closelog',
                'define_syslog_variables',
                'ob_get_flush',
                'ob_get_level',
                'ob_get_status',
                'ob_list_handlers',
                'extract',
                'compact',
                'array_fill_keys',
                'array_intersect_uassoc',
                'pos',
                'sizeof',
                'key_exists',
                'ftok',
                'str_rot13',
                'stream_get_filters',
                'stream_filter_register',
                'stream_bucket_make_writeable',
                'stream_bucket_prepend',
                'stream_bucket_append',
                'stream_bucket_new',
                'output_add_rewrite_var',
                'output_reset_rewrite_vars',
                'sys_get_temp_dir',
                'preg_last_error',
                'spl_classes',
                'spl_autoload',
                'spl_autoload_extensions',
                'spl_autoload_register',
                'spl_autoload_unregister',
                'spl_autoload_functions',
                'spl_autoload_call',
                'class_parents',
                'class_implements',
                'spl_object_hash',
                'iterator_to_array',
                'iterator_count',
                'iterator_apply',
                'pdo_drivers',
                'sqlite_open',
                'sqlite_popen',
                'sqlite_close',
                'sqlite_query',
                'sqlite_exec',
                'sqlite_array_query',
                'sqlite_single_query',
                'sqlite_fetch_array',
                'sqlite_fetch_object',
                'sqlite_fetch_single',
                'sqlite_fetch_string',
                'sqlite_fetch_all',
                'sqlite_current',
                'sqlite_column',
                'sqlite_libversion',
                'sqlite_libencoding',
                'sqlite_changes',
                'sqlite_last_insert_rowid',
                'sqlite_num_rows',
                'sqlite_num_fields',
                'sqlite_field_name',
                'sqlite_seek',
                'sqlite_rewind',
                'sqlite_next',
                'sqlite_prev',
                'sqlite_valid',
                'sqlite_has_more',
                'sqlite_has_prev',
                'sqlite_escape_string',
                'sqlite_busy_timeout',
                'sqlite_last_error',
                'sqlite_error_string',
                'sqlite_unbuffered_query',
                'sqlite_create_aggregate',
                'sqlite_create_function',
                'sqlite_factory',
                'sqlite_udf_encode_binary',
                'sqlite_udf_decode_binary',
                'sqlite_fetch_column_types',
                'simplexml_load_file',
                'simplexml_load_string',
                'simplexml_import_dom',
                'session_name',
                'session_module_name',
                'session_save_path',
                'session_id',
                'session_regenerate_id',
                'session_decode',
                'session_register',
                'session_unregister',
                'session_is_registered',
                'session_encode',
                'session_start',
                'session_destroy',
                'session_unset',
                'session_set_save_handler',
                'session_cache_limiter',
                'session_cache_expire',
                'session_set_cookie_params',
                'session_get_cookie_params',
                'session_write_close',
                'session_commit',
                'pspell_new',
                'pspell_new_personal',
                'pspell_new_config',
                'pspell_check',
                'pspell_suggest',
                'pspell_store_replacement',
                'pspell_add_to_personal',
                'pspell_add_to_session',
                'pspell_clear_session',
                'pspell_save_wordlist',
                'pspell_config_create',
                'pspell_config_runtogether',
                'pspell_config_mode',
                'pspell_config_ignore',
                'pspell_config_personal',
                'pspell_config_dict_dir',
                'pspell_config_data_dir',
                'pspell_config_repl',
                'pspell_config_save_repl',
                'posix_kill',
                'posix_getpid',
                'posix_getppid',
                'posix_getuid',
                'posix_setuid',
                'posix_geteuid',
                'posix_seteuid',
                'posix_getgid',
                'posix_setgid',
                'posix_getegid',
                'posix_setegid',
                'posix_getgroups',
                'posix_getlogin',
                'posix_getpgrp',
                'posix_setsid',
                'posix_setpgid',
                'posix_getpgid',
                'posix_getsid',
                'posix_uname',
                'posix_times',
                'posix_ctermid',
                'posix_ttyname',
                'posix_isatty',
                'posix_getcwd',
                'posix_mkfifo',
                'posix_mknod',
                'posix_access',
                'posix_getgrnam',
                'posix_getgrgid',
                'posix_getpwnam',
                'posix_getpwuid',
                'posix_getrlimit',
                'posix_get_last_error',
                'posix_errno',
                'posix_strerror',
                'posix_initgroups',
                'mysql_connect',
                'mysql_pconnect',
                'mysql_close',
                'mysql_select_db',
                'mysql_query',
                'mysql_unbuffered_query',
                'mysql_db_query',
                'mysql_list_dbs',
                'mysql_list_tables',
                'mysql_list_fields',
                'mysql_list_processes',
                'mysql_error',
                'mysql_errno',
                'mysql_affected_rows',
                'mysql_insert_id',
                'mysql_result',
                'mysql_num_rows',
                'mysql_num_fields',
                'mysql_fetch_row',
                'mysql_fetch_array',
                'mysql_fetch_assoc',
                'mysql_fetch_object',
                'mysql_data_seek',
                'mysql_fetch_lengths',
                'mysql_fetch_field',
                'mysql_field_seek',
                'mysql_free_result',
                'mysql_field_name',
                'mysql_field_table',
                'mysql_field_len',
                'mysql_field_type',
                'mysql_field_flags',
                'mysql_escape_string',
                'mysql_real_escape_string',
                'mysql_stat',
                'mysql_thread_id',
                'mysql_client_encoding',
                'mysql_ping',
                'mysql_get_client_info',
                'mysql_get_host_info',
                'mysql_get_proto_info',
                'mysql_get_server_info',
                'mysql_info',
                'mysql_set_charset',
                'mysql',
                'mysql_fieldname',
                'mysql_fieldtable',
                'mysql_fieldlen',
                'mysql_fieldtype',
                'mysql_fieldflags',
                'mysql_selectdb',
                'mysql_freeresult',
                'mysql_numfields',
                'mysql_numrows',
                'mysql_listdbs',
                'mysql_listtables',
                'mysql_listfields',
                'mysql_db_name',
                'mysql_dbname',
                'mysql_tablename',
                'mysql_table_name',
                'mb_convert_case',
                'mb_strtoupper',
                'mb_strtolower',
                'mb_language',
                'mb_internal_encoding',
                'mb_http_input',
                'mb_http_output',
                'mb_detect_order',
                'mb_substitute_character',
                'mb_parse_str',
                'mb_output_handler',
                'mb_preferred_mime_name',
                'mb_strlen',
                'mb_strpos',
                'mb_strrpos',
                'mb_stripos',
                'mb_strripos',
                'mb_strstr',
                'mb_strrchr',
                'mb_stristr',
                'mb_strrichr',
                'mb_substr_count',
                'mb_substr',
                'mb_strcut',
                'mb_strwidth',
                'mb_strimwidth',
                'mb_convert_encoding',
                'mb_detect_encoding',
                'mb_list_encodings',
                'mb_convert_kana',
                'mb_encode_mimeheader',
                'mb_decode_mimeheader',
                'mb_convert_variables',
                'mb_encode_numericentity',
                'mb_decode_numericentity',
                'mb_send_mail',
                'mb_get_info',
                'mb_check_encoding',
                'mb_regex_encoding',
                'mb_regex_set_options',
                'mb_ereg',
                'mb_eregi',
                'mb_ereg_replace',
                'mb_eregi_replace',
                'mb_split',
                'mb_ereg_match',
                'mb_ereg_search',
                'mb_ereg_search_pos',
                'mb_ereg_search_regs',
                'mb_ereg_search_init',
                'mb_ereg_search_getregs',
                'mb_ereg_search_getpos',
                'mb_ereg_search_setpos',
                'mbregex_encoding',
                'mbereg',
                'mberegi',
                'mbereg_replace',
                'mberegi_replace',
                'mbsplit',
                'mbereg_match',
                'mbereg_search',
                'mbereg_search_pos',
                'mbereg_search_regs',
                'mbereg_search_init',
                'mbereg_search_getregs',
                'mbereg_search_getpos',
                'mbereg_search_setpos',
                'json_encode',
                'json_decode',
                'imap_open',
                'imap_reopen',
                'imap_close',
                'imap_num_msg',
                'imap_num_recent',
                'imap_headers',
                'imap_headerinfo',
                'imap_rfc822_parse_headers',
                'imap_rfc822_write_address',
                'imap_rfc822_parse_adrlist',
                'imap_body',
                'imap_bodystruct',
                'imap_fetchbody',
                'imap_savebody',
                'imap_fetchheader',
                'imap_fetchstructure',
                'imap_expunge',
                'imap_delete',
                'imap_undelete',
                'imap_check',
                'imap_mail_copy',
                'imap_mail_move',
                'imap_mail_compose',
                'imap_createmailbox',
                'imap_renamemailbox',
                'imap_deletemailbox',
                'imap_subscribe',
                'imap_unsubscribe',
                'imap_append',
                'imap_ping',
                'imap_base64',
                'imap_qprint',
                'imap_8bit',
                'imap_binary',
                'imap_utf8',
                'imap_status',
                'imap_mailboxmsginfo',
                'imap_setflag_full',
                'imap_clearflag_full',
                'imap_sort',
                'imap_uid',
                'imap_msgno',
                'imap_list',
                'imap_lsub',
                'imap_fetch_overview',
                'imap_alerts',
                'imap_errors',
                'imap_last_error',
                'imap_search',
                'imap_utf7_decode',
                'imap_utf7_encode',
                'imap_mime_header_decode',
                'imap_thread',
                'imap_timeout',
                'imap_get_quota',
                'imap_get_quotaroot',
                'imap_set_quota',
                'imap_setacl',
                'imap_getacl',
                'imap_mail',
                'imap_header',
                'imap_listmailbox',
                'imap_getmailboxes',
                'imap_scanmailbox',
                'imap_listsubscribed',
                'imap_getsubscribed',
                'imap_fetchtext',
                'imap_scan',
                'imap_create',
                'imap_rename',
                'hash',
                'hash_file',
                'hash_hmac',
                'hash_hmac_file',
                'hash_init',
                'hash_update',
                'hash_update_stream',
                'hash_update_file',
                'hash_final',
                'hash_algos',
                'imagerotate',
                'imageantialias',
                'imagecreatefromgif',
                'imagecreatefromwbmp',
                'imagecreatefromxbm',
                'imagecreatefromgd',
                'imagecreatefromgd2',
                'imagecreatefromgd2part',
                'imagegif',
                'imagewbmp',
                'imagegd',
                'imagegd2',
                'imagedashedline',
                'imageftbbox',
                'imagefttext',
                'jpeg2wbmp',
                'png2wbmp',
                'image2wbmp',
                'imagelayereffect',
                'imagecolormatch',
                'imagexbm',
                'imageconvolution',
                'ftp_connect',
                'ftp_ssl_connect',
                'ftp_login',
                'ftp_pwd',
                'ftp_cdup',
                'ftp_chdir',
                'ftp_exec',
                'ftp_raw',
                'ftp_mkdir',
                'ftp_rmdir',
                'ftp_chmod',
                'ftp_alloc',
                'ftp_nlist',
                'ftp_rawlist',
                'ftp_systype',
                'ftp_pasv',
                'ftp_get',
                'ftp_fget',
                'ftp_put',
                'ftp_fput',
                'ftp_size',
                'ftp_mdtm',
                'ftp_rename',
                'ftp_delete',
                'ftp_site',
                'ftp_close',
                'ftp_set_option',
                'ftp_get_option',
                'ftp_nb_fget',
                'ftp_nb_get',
                'ftp_nb_continue',
                'ftp_nb_put',
                'ftp_nb_fput',
                'ftp_quit',
                'filter_input',
                'filter_var',
                'filter_input_array',
                'filter_var_array',
                'filter_list',
                'filter_has_var',
                'filter_id',
                'idate',
                'date_create',
                'date_parse',
                'date_format',
                'date_modify',
                'date_timezone_get',
                'date_timezone_set',
                'date_offset_get',
                'date_time_set',
                'date_date_set',
                'date_isodate_set',
                'timezone_open',
                'timezone_name_get',
                'timezone_name_from_abbr',
                'timezone_offset_get',
                'timezone_transitions_get',
                'timezone_identifiers_list',
                'timezone_abbreviations_list',
                'date_sunrise',
                'date_sunset',
                'date_sun_info',
                'curl_init',
                'curl_copy_handle',
                'curl_version',
                'curl_setopt',
                'curl_setopt_array',
                'curl_exec',
                'curl_getinfo',
                'curl_error',
                'curl_errno',
                'curl_close',
                'curl_multi_init',
                'curl_multi_add_handle',
                'curl_multi_remove_handle',
                'curl_multi_select',
                'curl_multi_exec',
                'curl_multi_getcontent',
                'curl_multi_info_read',
                'curl_multi_close',
                'ctype_alnum',
                'ctype_alpha',
                'ctype_cntrl',
                'ctype_digit',
                'ctype_lower',
                'ctype_graph',
                'ctype_print',
                'ctype_punct',
                'ctype_space',
                'ctype_upper',
                'ctype_xdigit',
                'bzopen',
                'bzread',
                'bzwrite',
                'bzflush',
                'bzclose',
                'bzerrno',
                'bzerrstr',
                'bzerror',
                'bzcompress',
                'bzdecompress',
                'gzrewind',
                'gzeof',
                'gzgetc',
                'gzgets',
                'gzgetss',
                'gzread',
                'gzpassthru',
                'gzseek',
                'gztell',
                'gzputs',
                'zlib_get_coding_type',
                'openssl_pkey_free',
                'openssl_pkey_new',
                'openssl_pkey_export',
                'openssl_pkey_export_to_file',
                'openssl_pkey_get_private',
                'openssl_pkey_get_public',
                'openssl_pkey_get_details',
                'openssl_free_key',
                'openssl_get_privatekey',
                'openssl_get_publickey',
                'openssl_x509_read',
                'openssl_x509_free',
                'openssl_x509_parse',
                'openssl_x509_checkpurpose',
                'openssl_x509_check_private_key',
                'openssl_x509_export',
                'openssl_x509_export_to_file',
                'openssl_pkcs12_export',
                'openssl_pkcs12_export_to_file',
                'openssl_pkcs12_read',
                'openssl_csr_new',
                'openssl_csr_export',
                'openssl_csr_export_to_file',
                'openssl_csr_sign',
                'openssl_csr_get_subject',
                'openssl_csr_get_public_key',
                'openssl_sign',
                'openssl_verify',
                'openssl_seal',
                'openssl_open',
                'openssl_pkcs7_verify',
                'openssl_pkcs7_decrypt',
                'openssl_pkcs7_sign',
                'openssl_pkcs7_encrypt',
                'openssl_private_encrypt',
                'openssl_private_decrypt',
                'openssl_public_encrypt',
                'openssl_public_decrypt',
                'openssl_error_string',
                'apache_lookup_uri',
                'virtual',
                'apache_request_headers',
                'apache_response_headers',
                'apache_setenv',
                'apache_getenv',
                'apache_note',
                'apache_get_version',
                'apache_get_modules',
                'getallheaders',
            );

            $defined = get_defined_functions();
            foreach ($defined['internal'] as $function) {
                if (!in_array($function, $will_never_define)) {
                    $this->assertTrue(in_array($function, $declared_functions), 'Should be defined? ' . $function);
                }
            }
        }
    }
}

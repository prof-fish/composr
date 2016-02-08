<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core
 */

/**
 * Open up a TAR archive, and return the resource.
 *
 * @param  ?PATH $path The path to the TAR archive (null: write out directly to stdout)
 * @param  string $mode The mode to open the TAR archive (rb=read, wb=write)
 * @set    rb wb w+b
 * @return array The TAR file handle
 */
function tar_open($path, $mode)
{
    if (is_null($path)) {
        $myfile = mixed();
        $exists = false;
    } else {
        $exists = file_exists($path) && (strpos($mode, 'a') !== false);
        $myfile = @fopen($path, $mode);
        if ($myfile === false) {
            if (substr($mode, 0, 1) == 'r') {
                warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
            } else {
                intelligent_write_error($path);
            }
        }
    }
    $resource = array();
    $resource['new'] = !$exists;
    $resource['mode'] = $mode;
    $resource['myfile'] = $myfile;
    $resource['full'] = $path;
    $resource['already_at_end'] = false;
    if (((!$exists) || (!(filesize($path) > 0))) && (strpos($mode, 'r') === false)) {
        $chunk = pack('a1024', '');
        if (!is_null($myfile)) {
            if (fwrite($myfile, $chunk) < strlen($chunk)) {
                warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
            }
        }
        $resource['directory'] = array();
        $resource['end'] = 0;
    }
    return $resource;
}

/**
 * Return the root directory from the specified TAR file. Note that there are folders in here, and they will end '/'.
 *
 * @param  array $resource The TAR file handle
 * @param  boolean $tolerate_errors Whether to tolerate errors (returns null if error)
 * @return ?array A list of maps that stores 'path', 'mode', 'size' and 'mtime', for each file in the archive (null: error)
 */
function tar_get_directory(&$resource, $tolerate_errors = false)
{
    if (array_key_exists('directory', $resource)) {
        return $resource['directory'];
    }

    $myfile = $resource['myfile'];
    $finished = false;
    fseek($myfile, 0, SEEK_SET);
    $resource['already_at_end'] = false;
    $directory = array();
    $next_name = mixed();

    do {
        if (feof($myfile)) {
            if ($tolerate_errors) {
                return null;
            }
            warn_exit(do_lang_tempcode('CORRUPT_TAR'));
        }

        $offset = ftell($myfile);
        $header = fread($myfile, 512);
        if (strlen($header) < 512) {
            if ($tolerate_errors) {
                return null;
            }
            warn_exit(do_lang_tempcode('CORRUPT_TAR'));
        }
        if (ord(substr($header, 0, 1)) == 0) {
            $finished = true;
            $resource['end'] = $offset;
        } else {
            if (substr($header, 257, 5) == 'ustar') {
                $path = str_replace('\\', '/', substr($header, 345, min(512, strpos($header, chr(0), 345) - 345)) . substr($header, 0, min(100, strpos($header, chr(0), 0))));
            } else {
                $path = substr($header, 0, min(100, strpos($header, chr(0), 0)));
            }
            if ($next_name !== null) {
                $path = $next_name;
            }

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $path = utf8_decode($path);
            }

            $mode = octdec(substr($header, 100, 8));
            $size = octdec(trim(substr($header, 124, 12)));
            $mtime = octdec(trim(substr($header, 136, 12)));
            $chksum = octdec(trim(substr($header, 148, 8)));
            $block_size = file_size_to_tar_block_size($size);
            //$is_ok = substr($header, 156, 1) == '0';  Actually, this isn't consistently useful

            $header2 = substr($header, 0, 148);
            $header2 .= '        ';
            $header2 .= substr($header, 156);
            if ($chksum != tar_crc($header2)) {
                if ($tolerate_errors) {
                    return null;
                }
                warn_exit(do_lang_tempcode('CORRUPT_TAR'));
            }

            //if ($is_ok) {
            if ($path != '././@LongLink') {
                if (substr(basename($path), 0, 2) != '._') {
                    $directory[$offset] = array('path' => $path, 'mode' => $mode, 'size' => $size, 'mtime' => $mtime);
                }
                $next_name = null;
                fseek($myfile, $block_size, SEEK_CUR);
            } else {
                fseek($myfile, 512, SEEK_CUR);
                $next_name = fread($myfile, $size);
                fseek($myfile, $block_size - 512 - $size, SEEK_CUR);
            }
            //}

            $resource['already_at_end'] = false;
        }
    } while (!$finished);

    $resource['directory'] = $directory;

    return $directory;
}

/**
 * Return the output from the conversion between filesize and TAR block size.
 *
 * @param  integer $size The file size of a file that would be inside the TAR archive
 * @return integer The block size TAR would use to store this file
 */
function file_size_to_tar_block_size($size)
{
    return ($size % 512 == 0) ? $size : (intval($size / 512) + 1) * 512;
}

/**
 * Add a folder to the TAR archive, however only store files modifed after a threshold time. It is incremental (incremental backup), by comparing against a threshold before adding a file (threshold being time of last backup)
 *
 * @param  array $resource The TAR file handle
 * @param  ?resource $logfile The logfile to write to (null: no logging)
 * @param  PATH $path The full path to the folder to add
 * @param  TIME $threshold The threshold time
 * @param  ?integer $max_size The maximum file size to add (null: no limit)
 * @param  PATH $subpath The subpath relative to the path (should be left as the default '', as this is used for the recursion to distinguish the adding base path from where it's currently looking)
 * @param  boolean $all_files Whether to not skip "special files" (ones not normally archive)
 * @return array A list of maps that stores 'path', 'mode' and 'size', for each newly added file in the archive
 */
function tar_add_folder_incremental(&$resource, $logfile, $path, $threshold, $max_size, $subpath = '', $all_files = false)
{
    require_code('files');

    $_full = ($path == '') ? $subpath : ($path . '/' . $subpath);
    if ($_full == '') {
        $_full = '.';
    }
    $info = array();
    if (!is_null($logfile)) {
        $dh = @opendir($_full);
        if ($dh === false) {
            if (fwrite($logfile, 'Could not access ' . $_full . "\n") == 0) {
                warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
            }
        }
    } else {
        $dh = opendir($_full);
    }
    if ($dh !== false) {
        while (($entry = readdir($dh)) !== false) {
            if (($entry == '.') || ($entry == '..')) {
                continue;
            }

            $_subpath = ($subpath == '') ? $entry : ($subpath . '/' . $entry);
            if (($all_files) || (!should_ignore_file($_subpath))) {
                $full = ($path == '') ? $_subpath : ($path . '/' . $_subpath);
                if (!is_readable($full)) {
                    if (fwrite($logfile, 'Could not access ' . $full . "\n") == 0) {
                        warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                    }
                    continue;
                }
                if (is_dir($full)) {
                    $info2 = tar_add_folder_incremental($resource, $logfile, $path, $threshold, $max_size, $_subpath, $all_files);
                    $info = array_merge($info, $info2);
                } else {
                    if (($full != $resource['full']) && ($full != 'DIRECTORY')) {
                        $ctime = filectime($full);
                        $mtime = filemtime($full);
                        if ((($mtime > $threshold || $ctime > $threshold)) && ((is_null($max_size)) || (filesize($full) < $max_size * 1024 * 1024))) {
                            tar_add_file($resource, $_subpath, $full, fileperms($full), filemtime($full), true);
                            if (!is_null($logfile) && fwrite($logfile, 'Backed up file ' . $_subpath . ' (' . clean_file_size(filesize($full)) . ')' . "\n") == 0) {
                                warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                            }
                        }
                        /* We don't store all this stuff, it's not in Composr's remit
                        $owner = fileowner($full);
                        $group = filegroup($full);
                        if (php_function_allowed('posix_getuid')) {
                            $owner = posix_getuid($owner);
                        }
                        if (php_function_allowed('posix_getgrgid')) {
                            $group = posix_getgrgid($group);
                        }
                        */
                        $perms = fileperms($full);
                        $info[] = array('path' => $full, 'size' => filesize($full),/* 'owner' => $owner, 'group' => $group,*/
                                        'perms' => $perms, 'ctime' => $ctime, 'mtime' => $mtime);
                    }
                }
            }
        }
        closedir($dh);
    }
    return $info;
}

/**
 * Add a folder to the TAR archive
 *
 * @param  array $resource The TAR file handle
 * @param  ?resource $logfile The logfile to write to (null: no logging)
 * @param  PATH $path The full path to the folder to add
 * @param  ?integer $max_size The maximum file size to add (null: no limit)
 * @param  PATH $subpath The subpath relative to the path (should be left as the default '', as this is used for the recursion to distinguish the adding base path from where it's currently looking)
 * @param  ?array $avoid_backing_up A map (filename=>true) of files to not back up (null: none)
 * @param  ?array $root_only_dirs A list of directories ONLY to back up from the root (null: no restriction)
 * @param  boolean $tick Whether to output spaces as we go to keep the connection alive
 * @param  boolean $all_files Whether to not skip "special files" (ones not normally archive)
 */
function tar_add_folder(&$resource, $logfile, $path, $max_size = null, $subpath = '', $avoid_backing_up = null, $root_only_dirs = null, $tick = false, $all_files = false) // Note we cannot modify $resource unless we pass it by reference
{
    require_code('files');

    $_full = ($path == '') ? $subpath : ($path . '/' . $subpath);
    if ($_full == '') {
        $_full = '.';
    }
    if (!is_null($logfile)) {
        $dh = @opendir($_full);
        if ($dh === false) {
            if (fwrite($logfile, 'Could not access ' . $_full . ' [case 2]' . "\n") == 0) {
                warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
            }
        }
    } else {
        $dh = opendir($_full);
    }
    if ($dh !== false) {
        while (($entry = readdir($dh)) !== false) {
            if (($entry == '.') || ($entry == '..')) {
                continue;
            }

            if ($tick) {
                @print(' ');
            }

            $_subpath = ($subpath == '') ? $entry : ($subpath . '/' . $entry);
            if (($all_files) || (!should_ignore_file($_subpath))) {
                $full = ($path == '') ? $_subpath : ($path . '/' . $_subpath);
                if (!is_readable($full)) {
                    if (fwrite($logfile, 'Could not access ' . $full . "\n") == 0) {
                        warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                    }
                    continue;
                }
                if (is_dir($full)) {
                    if ((is_null($root_only_dirs)) || (in_array($entry, $root_only_dirs))) {
                        tar_add_folder($resource, $logfile, $path, $max_size, $_subpath, $avoid_backing_up, null, $tick, $all_files);
                    }
                } else {
                    if ((($full != $resource['full']) && ((is_null($max_size)) || (filesize($full) < $max_size * 1024 * 1024))) && ((is_null($avoid_backing_up)) || (!array_key_exists($_subpath, $avoid_backing_up)))) {
                        tar_add_file($resource, $_subpath, $full, fileperms($full), filemtime($full), true);
                        if (!is_null($logfile) && fwrite($logfile, 'Backed up file ' . $_subpath . ' (' . clean_file_size(filesize($full)) . ')' . "\n") == 0) {
                            warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                        }
                    }
                }
            }
        }
        closedir($dh);
    }
}

/**
 * Extract all the files in the specified TAR file to the specified path.
 *
 * @param  array $resource The TAR file handle
 * @param  PATH $path The path to the folder to extract to, relative to the base directory
 * @param  boolean $use_afm Whether to extract via the AFM (assumes AFM has been set up prior to this function call)
 * @param  ?array $files The files to extract (null: all)
 * @param  boolean $comcode_backups Whether to take backups of Comcode pages
 */
function tar_extract_to_folder(&$resource, $path, $use_afm = false, $files = null, $comcode_backups = false)
{
    if (!array_key_exists('directory', $resource)) {
        tar_get_directory($resource);
    }

    if (substr($path, -1) != '/') {
        $path .= '/';
    }

    $directory = $resource['directory'];

    foreach ($directory as $file) {
        if (($file['path'] != 'addon.inf') && ($file['path'] != 'addon_install_code.php') && ((is_null($files)) || (in_array($file['path'], $files)))) {
            // Special case for directories. Composr doesn't add directory records, but at least 7-zip does
            if (substr($file['path'], -1) == '/') {
                if (!$use_afm) {
                    @mkdir(get_custom_file_base() . '/' . $path . $file['path'], 0777, true);
                    fix_permissions(get_custom_file_base() . '/' . $path . $file['path']);
                    sync_file(get_custom_file_base() . '/' . $path . $file['path']);
                } else {
                    afm_make_directory($path . $file['path'], true);
                }
                continue;
            }

            // Make directory where file will be extracted to
            $data = tar_get_file($resource, $file['path']);
            $path_components = explode('/', $file['path']);
            $buildup = '';
            foreach ($path_components as $i => $component) {
                if ($component != '') {
                    if (array_key_exists($i + 1, $path_components)) {
                        $buildup .= $component . '/';
                        if (!$use_afm) {
                            if (!file_exists(get_custom_file_base() . '/' . $path . $buildup)) {
                                @mkdir(get_custom_file_base() . '/' . $path . $buildup, 0777, true);
                                fix_permissions(get_custom_file_base() . '/' . $path . $buildup);
                                sync_file(get_custom_file_base() . '/' . $path . $buildup);
                            }
                        } else {
                            afm_make_directory($path . $buildup, true);
                        }
                    }
                }
            }

            // Take backup of Comcode page, if requested
            if ($comcode_backups) {
                if (substr($file['path'], -4) == '.txt') {
                    if (!$use_afm) {
                        if (file_exists(get_custom_file_base() . '/' . $path . $file['path'])) {
                            copy(get_custom_file_base() . '/' . $path . $file['path'], get_custom_file_base() . '/' . $path . $file['path'] . '.' . strval(time()));
                        }
                    } else {
                        if (file_exists(get_custom_file_base() . '/' . $path . $file['path'])) {
                            afm_copy($path . $file['path'], $path . $file['path'] . '.' . strval(time()), true);
                        }
                    }
                }
            }

            // Actually make file
            if (($path == '/') && ($comcode_backups) && (get_param_integer('keep_theme_test', 0) == 1) && (preg_match('#^[\w\_]+\.txt$#', basename($file['path'])) != 0)) {
                $theme = null;
                foreach ($directory as $file2) {
                    $matches = array();
                    if (preg_match('#^themes/([\w\_\-]+)/#', $file2['path'], $matches) != 0) {
                        $theme = $matches[1];
                        break;
                    }
                }
                if (!is_null($theme)) {
                    $file['path'] = dirname($file['path']) . '/' . $theme . '__' . basename($file['path']);
                }
            }
            if (!$use_afm) {
                if (file_exists(get_custom_file_base() . '/' . $path . $file['path'])) {
                    $changed = (file_get_contents(get_custom_file_base() . '/' . $path . $file['path']) != $data['data']);
                    if (!$changed) {
                        continue; // So old mtime can stay as is
                    }
                }

                $myfile = @fopen(get_custom_file_base() . '/' . $path . $file['path'], 'wb');
                if ($myfile === false) {
                    intelligent_write_error(get_custom_file_base() . '/' . $path . $file['path']);
                }
                if (fwrite($myfile, $data['data']) < strlen($data['data'])) {
                    warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                }
                $full_path = get_custom_file_base() . '/' . $path . $file['path'];
                @chmod($full_path, $data['mode']);
                if ($data['mtime'] == 0) {
                    $data['mtime'] = time();
                }
                @touch($full_path, $data['mtime']);
                fclose($myfile);
                fix_permissions($full_path);
                sync_file($full_path);
            } else {
                afm_make_file($path . $file['path'], $data['data'], ($data['mode'] & 0002) != 0);
            }
        }
    }
}

/**
 * Get the contents of the specified file in the specified TAR.
 *
 * @param  array $resource The TAR file handle
 * @param  PATH $path The full path to the file we want to get
 * @param  boolean $tolerate_errors Whether to tolerate errors (returns null if error)
 * @param  ?PATH $write_data_to Write data to here (null: return within array)
 * @return ?array A map, containing 'data' (the file), 'size' (the filesize), 'mtime' (the modification timestamp), and 'mode' (the permissions) (null: not found / TAR possibly corrupt if we turned tolerate errors on)
 */
function tar_get_file(&$resource, $path, $tolerate_errors = false, $write_data_to = null)
{
    if (!array_key_exists('directory', $resource)) {
        $ret = tar_get_directory($resource, $tolerate_errors);
        if (is_null($ret)) {
            return null;
        }
    }

    $directory = $resource['directory'];

    foreach ($directory as $offset => $stuff) {
        if ($stuff['path'] == $path) {
            if (!is_null($write_data_to)) {
                $outfile = fopen($write_data_to, 'wb');
            }

            if ($stuff['size'] == 0) {
                $data = '';
            } else {
                fseek($resource['myfile'], $offset + 512, SEEK_SET);
                $resource['already_at_end'] = false;
                $data = '';
                $len = 0;
                while ($len < $stuff['size']) {
                    $read_amount = min(4096, $stuff['size'] - strlen($data));
                    $test = fread($resource['myfile'], $read_amount);
                    if ($test === false || $test === null || $test == '') {
                        break;
                    }
                    $data .= $test;
                    $len += strlen($test);

                    if (!is_null($write_data_to)) {
                        if (fwrite($outfile, $data) < strlen($data)) {
                            warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                        }

                        $data = '';
                    }
                }
            }

            if (!is_null($write_data_to)) {
                fclose($outfile);
                fix_permissions($path);
                sync_file($path);
            }

            return array('data' => &$data, 'size' => $stuff['size'], 'mode' => $stuff['mode'], 'mtime' => $stuff['mtime']);
        }
    }
    return null;
}

/**
 * Add a file to the specified TAR file.
 *
 * @param  array $resource The TAR file handle
 * @param  PATH $target_path The relative path to where we wish to add the file to the archive (including filename)
 * @param  string $data The data of the file to add
 * @param  integer $_mode The file mode (permissions)
 * @param  ?TIME $_mtime The modification time we wish for our file (null: now)
 * @param  boolean $data_is_path Whether the $data variable is actually a full file path
 * @param  boolean $return_on_errors Whether to return on errors
 * @param  boolean $efficient_mode Don't do duplicate checks
 * @return integer Offset of the file in the TAR
 */
function tar_add_file(&$resource, $target_path, $data, $_mode = 0644, $_mtime = null, $data_is_path = false, $return_on_errors = false, $efficient_mode = false)
{
    if (is_null($_mtime)) {
        $_mtime = time();
    }

    $get_directory = !isset($resource['directory']);
    if ($get_directory) {
        tar_get_directory($resource);
    }

    if (substr($target_path, 0, 1) == '/') {
        $target_path = substr($target_path, 1);
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $target_path = utf8_encode($target_path);
    }

    $directory = $resource['directory'];

    if ($target_path != '././@LongLink' && !$efficient_mode) {
        foreach ($directory as $offset => $entry) { // Make sure it does not exist
            if ($entry['path'] == $target_path) {
                if ($return_on_errors) {
                    return $offset + 512;
                }
                warn_exit(do_lang_tempcode('FILE_IN_ARCHIVE_TWICE', escape_html($target_path)));
            }
        }

        if (strlen($target_path) > 100) {
            tar_add_file($resource, '././@LongLink', $target_path, $_mode, $_mtime, false, $return_on_errors);
        }
    }

    $myfile = $resource['myfile'];

    //if (!$resource['already_at_end']) {   Don't trust this as reliable at the moment and seeking is not a problem
    if (!is_null($myfile)) {
        fseek($myfile, $resource['end'], SEEK_SET);
    }
    $resource['already_at_end'] = true;
    //}
    $offset = $resource['end'];
    $resource['directory'][$resource['end']] = array('path' => $target_path, 'mode' => $_mode, 'size' => $data_is_path ? filesize($data) : strlen($data));

    if (strlen($target_path) > 100) {
        $slash_pos = strpos(substr($target_path, strlen($target_path) - 100), '/');
        if ($slash_pos === false) { // Must chop off start of filename because $prefix must be a directory :S
            $slash_pos = 0;
            $target_path = substr($target_path, 0, strrpos(substr($target_path, 0, -100), '/')) . substr($target_path, -100);
        } else {
            $slash_pos++;
        }
        $prefix_length = strlen($target_path) - 100 + $slash_pos;
        $prefix = rtrim(pack('a155', substr($target_path, 0, $prefix_length)), '/');
        $name = pack('a100', substr($target_path, $prefix_length));
    } else {
        $prefix = pack('a155', '');
        $name = pack('a100', $target_path);
    }

    $mode = sprintf('%7s ', decoct($_mode));
    $uid = sprintf('%7s ', decoct(fileowner(get_file_base() . '/index.php')));
    if (strlen($uid) > 8) {
        $uid = '        ';
    }
    $gid = sprintf('%7s ', decoct(filegroup(get_file_base() . '/index.php')));
    if (strlen($gid) > 8) {
        $gid = '        ';
    }
    $size = sprintf('%11s ', decoct($data_is_path ? filesize($data) : strlen($data)));
    $mtime = sprintf('%11s ', decoct($_mtime));
    $chksum = '        ';
    $typeflag = pack('a1', ($target_path == '././@LongLink') ? 'L' : '');
    $linkname = pack('a100', '');
    $magic = pack('a6', 'ustar');
    $version = pack('a2', '');
    $uname = pack('a8', '');
    $gname = pack('a8', '');
    $devmajor = pack('a8', '');
    $devminor = pack('a8', '');

    $whole = pack('a512', $name . $mode . $uid . $gid . $size . $mtime . $chksum . $typeflag . $linkname . $magic . $version . $uname . $gname . $devmajor . $devminor . $prefix);

    $checksum = tar_crc($whole);

    $chksum = pack('a8', decoct($checksum) . ' ');
    $whole = pack('a512', $name . $mode . $uid . $gid . $size . $mtime . $chksum . $typeflag . $linkname . $magic . $version . $uname . $gname . $devmajor . $devminor . $prefix);

    $chunk = pack('a512', $whole);
    if (is_null($myfile)) {
        echo $chunk;
    } else {
        if (fwrite($myfile, $chunk) < strlen($chunk)) {
            warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
        }
    }

    $block_size = file_size_to_tar_block_size($data_is_path ? filesize($data) : strlen($data));
    if ($data_is_path) {
        $infile = fopen($data, 'rb');
        while (!feof($infile)) {
            $in = fread($infile, 8000);
            if (is_null($myfile)) {
                echo $in;
            } else {
                if (fwrite($myfile, $in) < strlen($in)) {
                    warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                }
            }
        }
        fclose($infile);
        $extra_to_write = $block_size - filesize($data);
        if ($extra_to_write != 0) {
            if (is_null($myfile)) {
                echo pack('a' . strval($extra_to_write), '');
            } else {
                if (fwrite($myfile, pack('a' . strval($extra_to_write), '')) == 0) {
                    warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
                }
            }
        }
    } else {
        $chunk = pack('a' . strval($block_size), $data);
        if (is_null($myfile)) {
            echo $chunk;
        } else {
            if (fwrite($myfile, $chunk) < strlen($chunk)) {
                warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
            }
        }
    }
    $resource['end'] += $block_size + 512;

    return $offset + 512;
}

/**
 * Find the checksum specified in a TAR header
 *
 * @param  string $header The header from a TAR file
 * @return integer The checksum
 */
function tar_crc($header)
{
    $checksum = 0;
    for ($i = 0; $i < 512; $i++) {
        $checksum += ord(substr($header, $i, 1));
    }

    return $checksum;
}

/**
 * Close an open TAR resource.
 *
 * @param  array $resource The TAR file handle to close
 */
function tar_close($resource)
{
    if (substr($resource['mode'], 0, 1) != 'r') {
        if (is_null($resource['myfile'])) {
            $chunk = pack('a1024', '');
            echo $chunk;
        } else {
            $chunk = pack('a1024', '');
            if (fwrite($resource['myfile'], $chunk) < strlen($chunk)) {
                warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
            }

            fclose($resource['myfile']);
            fix_permissions($resource['full']);
        }
    } else {
        fclose($resource['myfile']);
    }
}

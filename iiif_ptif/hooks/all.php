<?php
    # This plugin generates Tiled Pyramidal TIFF files when uploading a new image

    # Convert image to PTIF after the upload is successful
    function HookIiif_ptifAllUploadfilesuccess($resourceId)
    {
        global $iiif_ptif_commands;

        if(!isGeneratePtif($resourceId)) {
            return;
        }

        # Get the path to the original image. We need to select the extension from the database for this
        $extension = sql_value("SELECT file_extension value FROM resource WHERE ref = '" . escape_check($resourceId) . "'", 'tif');
        $sourcePath = get_resource_path($resourceId, true, '', true, $extension);
        $destPath = getPtifFilePath($resourceId);

        $catchallCommand = null;
        $processed = false;

        # Loop through the list of available conversion commands
        foreach($iiif_ptif_commands as $command) {
            # Find the catchall command
            if(in_array('*', $command['extensions'])) {
                $catchallCommand = $command;
            }
            # Find the appropriate command based on the extension
            else if(in_array($extension, $command['extensions'])) {
                $processed = true;
                executeConversion($command, $sourcePath, $destPath, $resourceId);
            }
        }

        # If no appropriate command was found based on the extension, use the catchall command
        if(!$processed) {
            executeConversion($catchallCommand, $sourcePath, $destPath, $resourceId);
        }

        executeImagehubCommands($resourceId);
    }

    # Execute conversion when image is being replaced or edited with transform tools
    function HookIiif_ptifAllAdditional_replace_existing($ref, $log_ref)
    {
        HookIiif_ptifAllUploadfilesuccess($ref);
    }

    # Return the path where to store PTIF files in when uploading a new image
    function getPtifFilePath($ref, $forcedFolder = NULL)
    {
        global $storagedir, $iiif_ptif_filestore, $iiif_ptif_public_folder, $iiif_ptif_private_folder;

        $dir = $storagedir . $iiif_ptif_filestore;

        if($forcedFolder != NULL) {
            $dir .= $forcedFolder;
        } else if(isPublicImage($ref)) {
            $dir .= $iiif_ptif_public_folder;
        } else {
            $dir .= $iiif_ptif_private_folder;
        }

        # Create the directory to store the PTIF image if it does not yet exist
        if(!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . $ref . '.tif';
    }

    # Determine if this image should be made publicly available or not
    function isPublicImage($ref)
    {
        global $iiif_ptif_public_key, $iiif_ptif_public_value, $iiif_ptif_public_folder;

        $public = false;
        if($iiif_ptif_public_key != NULL && $iiif_ptif_public_folder != null) {
            $data = get_resource_field_data($ref);

            foreach($data as $field) {
                if ($field['name'] == $iiif_ptif_public_key) {
                    if(strpos($field['value'], $iiif_ptif_public_value) !== false) {
                        $public = true;
                        break;
                    }
                }
            }
        }
        return $public;
    }

    function getPtifQuality($ref)
    {
        global $iiif_ptif_quality_field;

        $data = get_resource_field_data($ref);
        foreach($data as $field) {
            if ($field['name'] == $iiif_ptif_quality_field) {
                if(!empty($field['value'])) {
                    if($field['value'] == '100' || preg_match('/[1-9][0-9]?/', $field['value'])) {
                        return $field['value'];
                    }
                }
                break;
            }
        }
        return '100';
    }

    function isGeneratePtif($ref)
    {
        global $iiif_generate_ptif_field;

        $data = get_resource_field_data($ref);
        foreach($data as $field) {
            if ($field['name'] == $iiif_generate_ptif_field) {
                if(!empty($field['value'])) {
                    return true;
                }
                break;
            }
        }
        return false;
    }

    # Execute the actual image conversion
    function executeConversion($command, $sourcePath, $destPath, $resourceId)
    {
        $destPath = escapeshellarg($destPath);

        # Append prefix to the output filename if needed
        if(array_key_exists('dest_prefix', $command)) {
            $destPath = $command['dest_prefix'] . $destPath;
        }

        # Append postfix to the output filename if needed
        if(array_key_exists('dest_postfix', $command)) {
            $destPath = $destPath . $command['dest_postfix'];
        }

        $sourcePath = escapeshellarg($sourcePath);

        # Append the arguments to the command
        if(array_key_exists('arguments', $command)) {
            $sourcePath = $command['arguments'] . ' ' . $sourcePath;
        }

        $cmd = $command['command'] . ' ' . $sourcePath . ' ' . $destPath;

        $cmd = str_replace('#ptif_quality#', getPtifQuality($resourceId), $cmd);

        run_command($cmd);
    }

    # Perform either command line or cURL calls to the Imagehub to import data from the datahub and generate IIIF manifests
    function executeImagehubCommands($resourceId)
    {
        global $iiif_imagehub_commands, $iiif_imagehub_curl_calls;

        if(isset($iiif_imagehub_commands)) {
            foreach($iiif_imagehub_commands as $key => $command) {
                $cmd = str_replace('{ref}', $resourceId, $command);
                run_command($cmd);
            }
        }
        if(isset($iiif_imagehub_curl_calls)) {
            foreach($iiif_imagehub_curl_calls as $key => $url) {
                $url = str_replace('{ref}', $resourceId, $url);

                $handle = curl_init($url);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
                curl_exec($handle);
                curl_close($handle);
            }
        }
    }


    # Delete any generated PTIF files associated with this resource when the resource is being deleted
    # Requires $resource_deletion_state to be set to NULL due to a bug in ResourceSpace where nothing is actually deleted otherwise
    # This bug resides in include/resource_functions.php:2015.
    function HookIiif_ptifAllBeforedeleteresourcefromdb($ref)
    {
        $path = getPtifFilePath($ref);
        if(file_exists($path)) {
            unlink($path);
            executeImagehubCommands($ref);
        }
    }

    function HookIiif_ptifAllUpdate_field($ref, $field, $value, $existing)
    {
        HookIiif_ptifAllAftersaveresourcedata(array($ref), null, null, null);
    }

    function HookIiif_ptifAllUpdate_resource_node($ref)
    {
        HookIiif_ptifAllAftersaveresourcedata(array($ref), null, null, null);
    }

    # In case the public use field has updated, move the PTIF to the correct subdirectory
    # If the 'Generate PTIF' field has updated, generate or delete it depending on the value
    # WARNING: does not trigger when a field is edited through the ResourceSpace API
    function HookIiif_ptifAllAftersaveresourcedata($refs, $nodes_to_add, $nodes_to_remove, $autosave_field)
    {
        if(!is_array($refs)) {
            $refs = array($refs);
        }
        foreach($refs as $ref) {
            if(!isGeneratePtif($ref)) {
                $path = getPtifFilePath($ref);
                if(file_exists($path)) {
                    unlink($path);
                    executeImagehubCommands($ref);
                }
            } else {
                if(movePtifToCorrectFolder($ref)) {
                    executeImagehubCommands($ref);
                } else {
                    $path = getPtifFilePath($ref);
                    if(!file_exists($path)) {
                        HookIiif_ptifAllUploadfilesuccess($ref);
                        executeImagehubCommands($ref);
                    }
                }
            }
        }
    }

    # A hackish way to move the PTIF to the correct subdirectory in case the public use field has updated through the API,
    # the application that performed the API call should perform a do_search call after, which will trigger this function
    function HookIiif_ptifAllBeforereturnresults($result, $archive)
    {
// Currently commented out, because this causes massive performance issues with large amounts of resources
//        foreach($result as $resource) {
//            movePtifToCorrectFolder($resource['ref']);
//        }
    }

    # Move the PTIF to the correct public/private folder if needed
    function movePtifToCorrectFolder($ref)
    {
        global $iiif_ptif_public_folder, $iiif_ptif_private_folder;

        if(isPublicImage($ref)) {
            $oldFile = getPtifFilePath($ref, $iiif_ptif_private_folder);
            if(file_exists($oldFile)) {
                rename($oldFile, getPtifFilePath($ref));
                return true;
            }
        } else {
            $oldFile = getPtifFilePath($ref, $iiif_ptif_public_folder);
            if(file_exists($oldFile)) {
                rename($oldFile, getPtifFilePath($ref));
                return true;
            }
        }
        return false;
    }

    # Renders clickable URL's to IIIF viewers above the preview image when opening a resource
    # Configure the $iiif_ptif_viewers field in config.php to generate appropriate URL's
    function HookIiif_ptifAllRenderbeforeresourceview($resource)
    {
        global $iiif_imagehub_manifest_url, $iiif_imagehub_viewers, $iiif_ptif_public_folder, $iiif_ptif_private_folder;
        $publicFolder = rtrim($iiif_ptif_public_folder, '/');
        $privateFolder = rtrim($iiif_ptif_private_folder, '/');

        if(isset($iiif_imagehub_manifest_url) && isset($iiif_imagehub_viewers)) {
            $url = str_replace('{ref}', $resource['ref'], $iiif_imagehub_manifest_url);
            $handle = curl_init($url);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($handle, CURLOPT_NOBODY, true);
            $response = curl_exec($handle);
            $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            if($httpCode == 200) {
                foreach($iiif_imagehub_viewers as $key => $viewer) {
                    $viewerUrl = str_replace('{manifest_url}', $url, $viewer);
                    $viewerUrl = str_replace('{ref}', $resource['ref'], $viewerUrl);
                    if(isPublicImage($resource['ref'])) {
                        $viewerUrl = str_replace('{dir}', $publicFolder, $viewerUrl);
                    } else {
                        $viewerUrl = str_replace('{dir}', $privateFolder, $viewerUrl);
                    }
                    echo '<p><a href="' . $viewerUrl . '" target="_blank">View ' . $key . '</a></p>';
                }
            } else {
                foreach($iiif_imagehub_viewers as $key => $viewer) {
                    echo '<p>There is currently no working link to ' . $key . ' yet.</p>';
                }
            }
        }
    }
?>

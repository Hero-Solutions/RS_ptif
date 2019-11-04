<?php
    # This plugin generates Tiled Pyramidal TIFF files when uploading a new image

    # Convert image to PTIF after the upload is successful
    function HookIiif_ptifAllUploadfilesuccess($resourceId)
    {
        global $iiif_ptif_commands;

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
                executeConversion($command, $sourcePath, $destPath);
            }
        }

        # If no appropriate command was found based on the extension, use the catchall command
        if(!$processed) {
            executeConversion($catchallCommand, $sourcePath, $destPath);
        }
    }

    # Return the path where to store PTIF files in when uploading a new image
    function getPtifFilePath($ref)
    {
        global $storagedir, $iiif_ptif_filestore, $iiif_ptif_public_key, $iiif_ptif_public_value, $iiif_ptif_public_folder, $iiif_ptif_private_folder;

        # Determine if this image should be made publicly available or not
        $public = false;
        if($iiif_ptif_public_key != NULL && $iiif_ptif_public_folder != null) {
            $data = get_resource_field_data($ref);
            foreach($data as $index => $field) {
                if($field['name'] == $iiif_ptif_public_key && strpos($field['value'], $iiif_ptif_public_value) > -1) {
                    $public = true;
                    break;
                }
            }
        }

        $dir = $storagedir . $iiif_ptif_filestore . ($public ? $iiif_ptif_public_folder : $iiif_ptif_private_folder);

        # Create the directory to store the PTIF image if it does not yet exist
        if(!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . $ref . '.tif';
    }

    # Execute the actual image conversion
    function executeConversion($command, $sourcePath, $destPath)
    {
        $destPath = escapeshellarg($destPath);

        # Append prefix to the output filename if needed
        if(in_array('prefix', $command)) {
            $destPath = $command['prefix'] . $destPath;
        }

        # Append postfix to the output filename if needed
        if(in_array('postfix', $command)) {
            $destPath = $destPath . $command['postfix'];
        }

        $sourcePath = escapeshellarg($sourcePath);

        # Append the arguments to the command
        if(in_array('arguments', $command)) {
            $sourcePath = $command['arguments'] . ' ' . $sourcePath;
        }

        $cmd = $command['command'] . ' ' . $sourcePath . ' ' . $destPath;

        $output = run_command($cmd);
    }

    # Delete any generated PTIF files associated with this resource when the resource is being deleted
    # Requires $resource_deletion_state to be set to NULL due to a bug in ResourceSpace where nothing is actually deleted otherwise
    # This bug resides in include/resource_functions.php:2015.
    function HookIiif_ptifAllBeforedeleteresourcefromdb($ref)
    {
        $path = getPtifFilePath($ref);
        if(file_exists($path)) {
            unlink($path);
        }
    }
?>

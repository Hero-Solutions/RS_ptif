<?php
    # This plugin generates Tiled Pyramidal TIFF files when uploading a new image

    # Return the path '/filestore/iiif/$ref.tif' to store PTIF files in when uploading a new image
    function getPtifFilePath($ref)
    {
        global $storagedir, $iiif_ptif_filestore;
        if(!file_exists($storagedir . $iiif_ptif_filestore)) {
            mkdir($storagedir . $iiif_ptif_filestore);
        }
        return $storagedir . $iiif_ptif_filestore . $ref . '.tif';
    }

    # Delete any generated PTIF files associated with this resource when the resource is being deleted
    function HookIiif_ptifAllBeforedeleteresourcefromdb($ref)
    {
      	$path = getPtifFilePath($ref);
        if(file_exists($path)) {
    	   unlink($path);
        }
    }

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

    function executeConversion($command, $sourcePath, $destPath)
    {
        if(strpos($command['command'], 'vips') > -1) {
            # vips requires the arguments to be appended to the target filename, separated with ':'
            $cmd = $command['command'] . ' ' . escapeshellarg($sourcePath) . ' ' . escapeshellarg($destPath) . ':' . $command['arguments'];
        } else if(in_array('prefix', $command)) {
            # convert requires the prefix ptif: in order to convert to Tiled Pyramidal TIFFs
            $cmd = $command['command'] . ' ' . $command['arguments'] . ' ' . $command['prefix'] . escapeshellarg($sourcePath) . ' ' . escapeshellarg($destPath);
        } else {
            $cmd = $command['command'] . ' ' . $command['arguments'] . ' ' . escapeshellarg($sourcePath) . ' ' . escapeshellarg($destPath);
        }

        $output = run_command($cmd);
    }

?>

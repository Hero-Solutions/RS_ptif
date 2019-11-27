<?php
    # This plugin performs command line calls to the Imagehub to import data from the datahub and 

    # Execute command line calls after file is uploaded
    function HookIiif_imagehubAllUploadfilesuccess($resourceId)
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

    # Renders clickable URL's to IIIF viewers above the preview image when opening a resource
    # Configure the $iiif_ptif_viewers field in config.php to generate appropriate URL's
    function HookIiif_imagehubAllRenderbeforeresourceview($resource)
    {
        global $iiif_imagehub_manifest_url, $iiif_imagehub_viewers;

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
                    $url = str_replace('{manifest_url}', urlencode($url), $viewer);
                    echo '<p><a href=' . $url . '>View in ' . $key . '</a></p>';
                }
            } else {
                foreach($iiif_imagehub_viewers as $key => $viewer) {
                    echo '<p>There is currently no working link to ' . $key . ' yet.</p>';
                }
            }
        }
    }

?>

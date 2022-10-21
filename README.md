# ResourceSpace PTIF

This project contains a ResourceSpace plugin to generate Tiled Pyramidal TIFF files and execute one or more commands when uploading a new image. This plugin is called when manually uploading an image or when an image is uploaded through the ResourceSpace API.

The plugin is highly configurable, you can choose where the PTIF files are to be stored relative to the filestore/ directory of your ResourceSpace installation, what metadata field to use to determine which images can be made publicly available and what command(s) or cUrl call(s) to use for conversion based on the extension of the uploaded file. You can also specify any commands to be executed after the image is uploaded.

There is also an optional configuration value ``$resource_deletion_state = NULL;``, necessary to work around a bug in ResourceSpace where images (as well as the generated PTIF) are not properly deleted when deleting a resource.

## Requirements

This project requires the following dependencies:
* [ResourceSpace](https://www.resourcespace.com/get) >= 9.1
* The command-line tools [convert](https://imagemagick.org/) or [vips](https://github.com/libvips/libvips) (possibly both), depending on which image conversion tool you need to use, as defined in the config below

## Considerations

If the CSV upload plugin is enabled, then you need to add the following lines in `include/node_functions.php` inthe ResourceSpace source code (at the end of each function, before the `return` statement if there is one), this will need to be done with every update of ResourceSpace. Perhaps this issue should be raised to the ResourceSpace developers so it can be integrated into the ResourceSpace codebase.

In `add_resource_nodes`, `delete_resource_nodes` and `delete_all_resource_nodes`:
```
hook('update_resource_node', '', array($resourceid));
```
In `add_resource_nodes_multi` and `delete_resource_nodes_multi`:
```
foreach($resources as $resourceid) {
    hook('update_resource_node', '', array($resourceid));
}
```
In `copy_resource_nodes`:
```
hook('update_resource_node', '', array($resourceto));
```

# Usage

In order to make use of this plugin, the ``iiif_ptif/`` folder should be copied to the ``plugins/`` folder of your ResourceSpace installation and activated by the system administrator (System -> Manage plugins, then expand the 'System' plugins and select the iiif_ptif plugin).

Also make sure that the webserver (for example www-data) has full write access to this plugin folder, so chmod and/or chown the each plugin directory if needed.

Add the following lines to the configuration file of your ResourceSpace installation (include/config.php). You can alter the values as needed for your particular setup.

```
# RS_ptif configuration

# This must be set to NULL in order to fix a bug within ResourceSpace
# where resource files are not properly deleted if this value is set to anything other than NULL.
# This bug resides in include/resource_functions.php:2015.
$resource_deletion_state = NULL;


# You can use either $iiif_imagehub_commands if the Imagehub is locally installed
# or $iiif_imagehub_curl_calls if the Imagehub is installed remotely

# Commands to be locally executed after an image is uploaded.
# {ref} will be automatically replaced by the corresponding resource ID by the plugin.
$iiif_imagehub_commands = array(
    '/opt/ImageHub/bin/console app:datahub-to-resourcespace {ref}',
    '/opt/ImageHub/bin/console app:generate-iiif-manifests {ref}'
);

# cURL calls to be executed after an image is uploaded.
# {ref} will be automatically replaced by the corresponding resource ID by the plugin.
$iiif_imagehub_curl_calls = array(
    'https://<imagehub-server>/datahub-to-resourcespace?ref={ref}&api_key=<api_key>',
    'https://<imagehub-server>/generate-iiif-manifests?ref={ref}&api_key=<api_key>'
);

# The URL of a IIIF manifest generated by one of the above commands.
# {ref} will be automatically replaced by the corresponding resource ID by the plugin.
$iiif_imagehub_manifest_url = 'https://<imagehub-server>/public/iiif/2/{ref}/manifest.json';

# Clickable URLs to be shown above the preview image in ResourceSpace.
# {manifest_url} will be automatically replaced by the manifest URL as defined in the line above by the plugin.
# We need to pass through the Imagehub authenticator first (which will redirect to the viewer through the 'url' GET parameter) when manifests or images are not publicly accessible.
$iiif_imagehub_viewers = array(
    'Universal Viewer' => 'https://<imagehub-server>/authenticate?url=https://<imagehub-server>/uv/index.php?manifest={manifest_url}',
    'Mirador'          => 'https://<imagehub-server>/authenticate?url=https://<imagehub-server>/mirador/index.php?manifest={manifest_url}',
    'Mirador V3'       => 'https://<imagehub-server>/authenticate?url=https://<imagehub-server>/mirador/3/index.php?manifest={manifest_url}'
);


# Name of the folder where the ptif files are stored (relative to the filestore/ directory).
# Must contain a leading and trailing slash.
$iiif_ptif_filestore = '/iiif_ptif/';

# Key (shorthand name of the metadata field) and value to determine which images are cleared for public
# Key can be set to NULL if all images should be private
$iiif_ptif_public_key = 'clearedforusage';
$iiif_ptif_public_value = 'Public use';

# Folder to store private images, trailing slash is important
$iiif_ptif_private_folder = 'private/';

# Folder to store public images, trailing slash is important
# Can be set to NULL if all images should be private
$iiif_ptif_public_folder = 'public/';

# CLI Commands to perform image conversion to PTIF.
# 'extensions' defines a list of file extensions and the command that should be used to convert images with these extensions to PTIF.
# 'command' should probably be 'vips im_vips2tiff' or 'convert', but accepts any installed command for image conversion (can be the full path to an executable).
# 'arguments' defines extra command line arguments for the conversion command.
# 'dest_prefix' will be prefixed to the destination path, necessary for convert.
# 'dest_postfix' will be postfixed to the destination path, necessary for vips.
$iiif_ptif_commands = array(
    # Use vips for TIFF images as it is generally faster and consumes fewer resources than convert, however it does not appear able to handle any other image formats
    array(
        'extensions'   => array('tif', 'tiff', 'ptif'),
        'command'      => 'vips im_vips2tiff',
        'arguments'    => '',
        'dest_prefix'  => '',
        'dest_postfix' => ':jpeg:#ptif_quality#,tile:256x256,pyramid'
    ),
    # define catchall command for all other extensions with '*'
    array(
        'extensions'   => array('*'),
        'command'      => 'convert',
        'arguments'    => '-define tiff:tile-geometry=256x256 -colorspace sRGB -compress jpeg -quality #ptif_quality# -depth 8',
        'dest_prefix'  => 'ptif:',
        'dest_postfix' => ''
    )
);

```

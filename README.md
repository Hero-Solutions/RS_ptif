# ResourceSpace PTIF

This project contains a ResourceSpace plugin to generate Tiled Pyramidal TIFF files when uploading a new image. This plugin is called when manually uploading an image or when an image is uploaded through the ResourceSpace API.
This plugin is highly configurable, you can choose where the PTIF files are to be stored relative to the filestore/ directory of your ResourceSpace installation, what metadata field to use to determine which images can be made publicly available and what command(s) to use for conversion based on the extension of the uploaded file.
There is also an optional configuration value '$resource_deletion_state = NULL;', necessary to work around a bug in ResourceSpace where images (as well as the generated PTIF) are not properly deleted when deleting a resource.

## Requirements

This project requires following dependencies:
* [ResourceSpace](https://www.resourcespace.com/get) >= 9.1
* The command-line tools [convert](https://imagemagick.org/) or [vips](https://github.com/libvips/libvips) (possibly both), depending on which image conversion tool you need to use, as defined in the config below

# Usage

In order to make use of this plugin, the iiif_ptif/ folder should be copied to the plugins/ folder of your ResourceSpace installation and activated by the system administrator (System -> Manage plugins, under the 'System' plugins). Also make sure that the webserver (www-data or apache2) has full access to this plugin folder, so chmod the directory if needed.

The following lines should be added to the configuration file of your ResourceSpace installation (include/config.php). You can edit these according to your own preferences:

```
# Config values required by the iiif_ptif plugin.

# This should be set to NULL in order to fix a bug within ResourceSpace
# where resource files are not properly deleted if this value is set to anything other than NULL.
# This bug resides in include/resource_functions.php:2015.
$resource_deletion_state = NULL;


# Config values required by the iiif_ptif plugin.

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
# 'prefix' will be prefixed to the destination path, necessary for convert.
# 'postfix' will be postfixed to the destination path, necessary for vips.
$iiif_ptif_commands = array(
    # vips cannot properly handle psb, so we need to use convert instead.
    array(
        'extensions' => array('psb'),
        'command' => 'convert',
        'arguments' => '-define tiff:tile-geometry=256x256 -compress jpeg -quality 100 -depth 8',
        'prefix' => 'ptif:',
        'postfix' => ''
    ),
    # define catchall command for all other extensions with '*'
    # vips is generally faster and consumes fewer resources than convert, so use wherever possible.
    array(
        'extensions' => array('*'),
        'command' => 'vips im_vips2tiff',
        'arguments' => '',
        'prefix' => '',
        'postfix' => ':jpeg:100,tile:256x256,pyramid'
    )
);

```

# Polyclip Plugin

A plugin for managing uploads in a polymorphic manner.

## Features

* All binary attachment data is stored in a single database table (this is the aforementioned "polymorphic manner") rather than being added to each model with an attachment.
* Attachments are aliased so that any given model could have multiple attachments, aliased uniquely.
* Will generate thumbnails for images if configured to do so.
* Plugin does all of the heavy lifting.

## Installation

### Download

#### As an Archive

1. Click the big ol' **Downloads** button next to the project description.
1. Extract the archive to `app/plugins/polyclip`.

#### As a Submodule

1. `$ git submodule add git://github.com/robwilkerson/CakePHP-Polyclip-Plugin.git <path_to>/app/plugins/polyclip`
1. `$ git submodule init`
1. `$ git submodule update`

### Install

1. Open the `config/install/install.sql` file and replace `@DB_NAME@` with the name of your application database.
1. Create the supporting database schema.

        $ cd <path to polyclip plugin root>/config/install
        $ mysql < install.sql

1. Create the directories that will hold the physical files.

        $ cd <path to polyclip plugin root>/config/install
        $ ./install <path to polyclip plugin root>/webroot

### Usage

#### Tell the Model It Has an Attachment

##### The Simple, Stupid Version

If a model only needs one attachment, no thumbnails and you're willing to accept the default attachment alias (Attachment), it's as simply as attaching the `Attachable` behavior.

    class ModelThatHasAnAttachment extends AppModel {
      public $actsAs = array( 'Polyclip.Attachable' );

      ...
    }

##### Aliased, Without Thumbnails

If a model can have multiple attachments, the attachments must be aliased uniquely. This example also shows the use of custom labels that are rendered in the available form element (more on that below). If custom labels are not provided, the alias name is used.

    class ModelThatHasDownloadableInstructions extends AppModel {
      public $actsAs = array(
        'Polyclip.Attachable' => array(
          'PDF'       => array( 'label' => 'PDF' ), 
          'MSWordDoc' => array( 'label' => 'MSWord Doc' ) ),  
      );

      ...
    }

##### The Kitchen Sink Version

The most common use case I've found involves a model with an image attachment for which I want to create one or more thumnbnails of varying sizes.

        class Sponsor extends AppModel {
          public $actsAs = array(
            'Polyclip.Attachable' => array(
              'Logo' => array
                'label' => 'Sponsor Logo',
                'Thumbnails' => array(
                  'homepage' => array( 'width' => 190, 'height' => 190, 'method' => 'resize_to_fit' ),
                  'square'   => array( 'width' => 100, 'height' => 100, 'method' => 'resize_to_fill' ),
                )
              )
            )
          );

          ...
        }

In this case, the attachment is aliased (`Logo`), has a custom label and 2 thumbnails. Thumbnails must be aliased uniquely and have their size and creation method specified.

* The thumbnail `width` and `height` values identify the _max_ height and width, not necessarily the final thumbnail dimensions.
* The thumbnail `method` option identifies the technique that will be used to generate the thumbnail from the uploaded original.
  * `resize_to_fit` maintains the original aspect ratio. The image will be scaled such that the largest dimension doesn't exceed its defined max and the other dimension will be scaled accordingly.
  * `resize_to_fill` resizes the image to whichever dimension needs to shrink less and crops the other by clipping half of the "leftover" from each side.

In this example, the `homepage` thumbnail will be no larger than 190x190 and the `square` thumbnail will be exactly 100x100.

#### Attach a File

The plugin contains a simple form element to assist with the process of getting a file from the user by handling the plugin expectations. The element will group all file fields.

    <?php echo $this->Form->create( 'Sponsor', array( 'type' => 'file' ) ) ?>
      <?php echo $this->Form->input( 'name' ) ?>
      
      # The attachment upload element
      <?php echo $this->element( 'attachments', array( 'plugin' => 'polyclip', 'data' => $this->data ) ); ?>
      
      <?php echo $this->Form->input( 'featured', array( 'label' => __( 'Should this sponsor be displayed on the homepage?', true ) ) ) ?>
    <?php echo $this->Form->end( __( 'Save', true ) ) ?>

## Credit

* This plugin is inspired by the various Rails plugins for handling attachments (e.g. `Attachment_fu`, `Paperclip`, etc.) and what I consider their limitations.
* Although I bastardized the crap out of it to meet my own context and standards, I borrowed heavily from [tute's Thumbnail Component for CakePHP](http://github.com/tute/Thumbnail-component-for-CakePHP/tree/f0aacea0b786df58df433cda535cf6c909508eb2).

## License

This code is licensed under the [MIT license](http://www.opensource.org/licenses/mit-license.php).

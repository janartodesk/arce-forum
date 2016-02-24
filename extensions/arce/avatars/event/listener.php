<?php

/**
*
* Resize logic is based on https://github.com/bb3mobi/AvatarUpload
*
* @package ARCE Avatars
* @copyright 2016 Janar Todesk
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace arce\avatars\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{

    /** Max size to display */
    private $avatar_size = 132;

    protected $request;
    protected $mimetype_guesser;

    public function __construct(\phpbb\request\request $request, \phpbb\mimetype\guesser $mimetype_guesser)
    {
        $this->request = $request;
        $this->mimetype_guesser = $mimetype_guesser;
    }

    static public function getSubscribedEvents()
    {
        return array(
            'core.avatar_driver_upload_move_file_before' => 'avatar_resize',
        );
    }

    public function avatar_resize($event)
    {
        // When we got an error, do nothing
        if(sizeof($event['error'])) return;

        $uploaded_file = $this->request->file('avatar_upload_file');

        // When there was something wrong with uploaded file, quit
        if (empty($uploaded_file['name'])) return;

        // Stash reference to the temporary file
        $source_file = $uploaded_file['tmp_name'];
        // Determine MIME-type of the image
        $mime_type = $this->mimetype_guesser->guess($source_file);
        // Get image size. Maybe there's a better way how phpBB does this, but... Try to find it
        list($width, $height) = getimagesize($source_file);
        // Create container for the final image
        $destination = imagecreatetruecolor($this->avatar_size, $this->avatar_size);

        switch ($mime_type)
        {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($source_file);
            break;

            case 'image/png':
                $source = imagecreatefrompng($source_file);
                $color = imagecolorallocatealpha($destination, 0, 0, 0, 127);
                imagefill($destination, 0, 0, $color);
            break;

            case 'image/gif':
                $source = imagecreatefromgif($source_file);
            break;
        }

        // Calculate the image ratio
        $ratio = $width / $height;

        // Calculate offsets to resize and crop the image into square
        $x = $ratio > 1 ? ($width - $height) / 2 : 0;
        $y = $ratio > 1 ? 0 : ($height - $width) / 2;

        $resize_width = $ratio > 1 ? floor($width * ($this->avatar_size / $height)) : $this->avatar_size;
        $resize_height = $ratio > 1 ? $this->avatar_size : floor($height * ($this->avatar_size / $height));

        imagecopyresampled($destination, $source, 0, 0, $x, $y, $resize_width, $resize_height, $width, $height);

        // Replace the temporary image
        switch ($mime_type)
        {
            case 'image/jpeg':
                imagejpeg($destination, $source_file, 70);
            break;

            case 'image/png':
                imagepng($destination, $source_file, 9);
            break;

            case 'image/gif':
                imagegif($destination, $source_file);
            break;
        }
    }
}

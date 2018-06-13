<?php

/**
 * Extending class of Jquery-file-upload. Adds property of creation date to files list
 * @author Tomas Plachy <plachy.t@gmail.com>
 * created: 13.06.2018
 */
class ExtendedUploadHandler extends UploadHandler
{

    protected function get_file_object($file_name)
    {
        if ($this->is_valid_file_object($file_name)) {
            $file = new stdClass();
            $file->name = $file_name;
            // get date modified of file
            $file->date = new DateTime();
            $file->date->setTimestamp(filemtime($this->get_upload_path($file_name)));
            $file->dateFormated = $file->date->format('d.m.Y H:i:s');
            $file->size = $this->get_file_size($this->get_upload_path($file_name));
            $file->url = $this->get_download_url($file->name);
            foreach ($this->options['image_versions'] as $version => $options) {
                if (!empty($version)) {
                    if (is_file($this->get_upload_path($file_name, $version))) {
                        $file->{$version . 'Url'} = $this->get_download_url(
                            $file->name, $version
                        );
                    }
                }
            }
            $this->set_additional_file_properties($file);
            return $file;
        }
        return null;
    }

    protected function handle_file_upload($uploaded_file,
        $name,
        $size,
        $type,
        $error,
        $index = null,
        $content_range = null)
    {
        $file = new stdClass();
        $file->name = $this->get_file_name($uploaded_file, $name, $size, $type, $error, $index, $content_range);
        $file->size = $this->fix_integer_overflow((int) $size);
        $file->type = $type;
        if ($this->validate($uploaded_file, $file, $error, $index)) {
            $this->handle_form_data($file, $index);
            $upload_dir = $this->get_upload_path();
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, $this->options['mkdir_mode'], true);
            }
            $file_path = $this->get_upload_path($file->name);
            $append_file = $content_range && is_file($file_path) &&
                $file->size > $this->get_file_size($file_path);
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    file_put_contents(
                        $file_path, fopen($uploaded_file, 'r'), FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $file_path, fopen($this->options['input_stream'], 'r'), $append_file ? FILE_APPEND : 0
                );
            }
            $file_size = $this->get_file_size($file_path, $append_file);
            // get date modified of file
            $file->date = new DateTime();
            $file->date->setTimestamp(filemtime($file_path));
            $file->dateFormated = $file->date->format('d.m.Y H:i:s');
            if ($file_size === $file->size) {
                $file->url = $this->get_download_url($file->name);
                if ($this->is_valid_image_file($file_path)) {
                    $this->handle_image_file($file_path, $file);
                }
            } else {
                $file->size = $file_size;
                if (!$content_range && $this->options['discard_aborted_uploads']) {
                    unlink($file_path);
                    $file->error = $this->get_error_message('abort');
                }
            }
            $this->set_additional_file_properties($file);
        }
        return $file;
    }
}

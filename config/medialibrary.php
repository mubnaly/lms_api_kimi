<?php

return [

    /*
     * The disk on which to store added files and derived images by default.
     * Choose one of your disks defined in `config/filesystems.php`.
     */
    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
     * The maximum file size of an item in bytes.
     * Adding a larger file will result in an exception.
     */
    'max_file_size' => 1024 * 1024 * 30, // 30 MB

    /*
     * This queue connection will be used to generate derived and responsive images.
     * Leave empty to use the default queue connection.
     */
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),

    /*
     * This queue will be used to generate derived and responsive images.
     */
    'queue_name' => '',

    /*
     * By default all conversions will be performed on a queue.
     */
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),

    /*
     * The fully qualified class name of the media model.
     */
    'media_model' => Spatie\MediaLibrary\MediaCollections\Models\Media::class,

    /*
     * The fully qualified class name of the model used to store temporary uploads.
     */
    'temporary_upload_model' => null, # Spatie\MediaLibraryPro\Models\TemporaryUpload::class,
    // 'temporary_upload_model' => App\Models\TemporaryUpload::class,

    /*
     * When enabled, media collections will be serialized using the media library's
     * default serialization behaviour when using `toArray` or `toJson`.
     */
    'use_default_collection_serialization' => true,

    /*
     * When enabled, Media Library Pro will be used to handle file uploads
     * and perform file validations.
     */
    'enable_media_library_pro' => false,
];

<?php

namespace App\Contracts;

interface TweetVideoServiceContract
{
    /**
     * Retrieve detailed information about a tweet using its URL.
     *
     * This method takes a tweet URL as input and returns an associative array
     * containing the tweet's data, such as the tweet ID, text, and media details.
     *
     * @param int $tweetId The ID of the tweet to retrieve.
     * @param bool $skipSignedRoute Whether to skip the signed route for the preview video.
     * @param bool $proxyPreviewImage Whether to proxy the preview image.
     * @return array|null Returns an associative array of tweet data or null if not found.
     */
    public function get(int $tweetId, bool $skipSignedRoute = false, bool $proxyPreviewImage = true): ?array;

    /**
     * Retrieve a thumbnail image from a tweet.
     *
     * This method takes a tweet ID as input and returns a stream of the thumbnail image.
     *
     * @param int $tweetId The ID of the tweet to retrieve the thumbnail image from.
     * @param int $index The index of the thumbnail image to retrieve.
     * @return array|null Returns an array containing the stream of the thumbnail image or null if not found.
     */
    public function imageThumbnail(int $tweetId, int $index = 0): ?array;

    /**
     * Stream a video from a tweet.
     *
     * This method takes a tweet ID as input and returns an array containing the stream of the video.
     *
     * @param int $tweetId The ID of the tweet to stream the video from.
     * @param int $index The index of the video to stream.
     * @param bool $isPreview Whether the video is a preview video.
     * @param int|null $bitrate The bitrate of the video to stream.
     * @param string|null $rangeHeader The range header of the video to stream.
     * @return array|null Returns an array containing the stream of the video or null if not found.
     */
    public function streamVideo(int $tweetId, int $index = 0, bool $isPreview = false, ?int $bitrate = null, ?string $rangeHeader = null): ?array;

    /**
     * Fetch a tweet from the API.
     *
     * This method takes a tweet ID as input and returns an array containing the tweet data.
     *
     * @param int $tweetId The ID of the tweet to fetch.
     * @param int $maxProcess The maximum number of accounts to fetch the tweet from.
     * @return array|null Returns an array containing the tweet data or null if not found.
     */
    public function fetchFromAPI(int $tweetId, int $maxProcess = 3): ?array;
}

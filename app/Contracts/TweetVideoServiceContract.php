<?php

namespace App\Contracts;

use Psr\Http\Message\StreamInterface;

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
     * @return array|null Returns an associative array of tweet data or null if not found.
     */
    public function get(int $tweetId, bool $skipSignedRoute = false): ?array;

    /**
     * Retrieve a thumbnail image from a tweet.
     *
     * This method takes a tweet ID as input and returns a stream of the thumbnail image.
     *
     * @param int $tweetId The ID of the tweet to retrieve the thumbnail image from.
     * @return StreamInterface|null Returns a stream of the thumbnail image or null if not found.
     */
    public function imageThumbnail(int $tweetId): ?StreamInterface;

    /**
     * Retrieve a video stream from a tweet.
     *
     * This method takes a tweet ID as input and returns a stream of the video.
     *
     * @param int $tweetId The ID of the tweet to retrieve the video stream from.
     * @return StreamInterface|null Returns a stream of the video or null if not found.
     * */
    public function streamVideo(int $tweetId): ?StreamInterface;
}

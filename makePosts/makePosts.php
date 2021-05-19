<?php

const BASE_URL = 'https://anxietydogcomic.com';
const POST_URL = BASE_URL . '/comic/';
const START_NUM = 1;
const END_NUM = 1;
const POST_PATH = '../_posts/';
const IMAGE_PATH = 'assets/images/comics/';
const DATE_FORMAT = 'Y-m-d';

function makePost(string $title, string $dateFormatted, string $imagePath, string $comment, string $transcript): string
{
    $output = <<<EOT
---
layout: post
title: "$title"
date: $dateFormatted
comic-source: "$imagePath"
transcript: "$transcript"
---

$comment

EOT;

    return $output;
}

function getHtml($url): DOMDocument
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    $content = curl_exec($ch);
    curl_close($ch);

    $doc = new DOMDocument();
    $doc->loadHTML($content);
    return $doc;
}

function getDateFormatted(DOMDocument $doc): string
{
    $possibleDates = $doc->getElementsByTagName('time');
    if ($possibleDates->length < 1) {
        throw new \Exception('Could not find post date');
    }

    $dateNode = $possibleDates->item(0);
    $dateTimeString = $dateNode->attributes->getNamedItem('datetime')->nodeValue;
    $date = new Datetime($dateTimeString);
    return $date->format(DATE_FORMAT);
}

function getPostTitle(DOMDocument $doc): string
{
    $possibleTitles = $doc->getElementsByTagName('h3');
    if ($possibleTitles->length < 1) {
        throw new \Exception('Could not find post title');
    }

    return $possibleTitles->item(0)->textContent;
}

function getPostText(DOMDocument $doc): string
{
    $blogPost = $doc->getElementById('blog-post');
    $text = $blogPost->getElementsByTagName('p')->item(0)->textContent;
    if (empty($text)) {
        throw new \Exception('Could not find post comment');
    }

    return $text;
}

function getTranscriptText(DOMDocument $doc): string
{
    $transcriptDiv = $doc->getElementById('transcript');
    if ($transcriptDiv == null) {
        return '';
    }
    $text = $transcriptDiv->getElementsByTagName('p')->item(0)->textContent;
    return $text;
}

function getImageUrl(DOMDocument $doc): string
{
    $imageNode = $doc->getElementsByTagName('figure')->item(0)->getElementsByTagName('img')->item(0);
    return trim($imageNode->attributes->getNamedItem('src')->nodeValue, '/');
}

function downloadImage(string $url): string
{
    $ch = curl_init(BASE_URL . '/' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    $imageContent = curl_exec($ch);
    curl_close($ch);

    $pathInfo = pathinfo($url);
    $imagePath = $pathInfo['filename'] . '.' . $pathInfo['extension'];

    file_put_contents('../' . IMAGE_PATH . $imagePath, $imageContent);
    return IMAGE_PATH . $imagePath;
}

function formatTitle(string $title): string
{
    return strtolower(preg_replace('/[\s-]+/', '-', $title));
}

for ($i = START_NUM; $i <= END_NUM; $i++) {
    echo POST_URL . $i . "\n";

    // Get HTML of page
    $html = getHtml(POST_URL . $i);

    try {
        $dateFormatted = getDateFormatted($html);
        $title = getPostTitle($html);
        $text = getPostText($html);
    } catch (\Exception $e) {
        echo $e->getMessage() . ' for post: ' . POST_URL . $i . PHP_EOL;
        exit;
    }

    // Find + download image
    $imageUrl = getImageUrl($html);
    $imagePath = downloadImage($imageUrl);

    // Create blog post
    $post = makePost($title, $dateFormatted, $imagePath, $text, getTranscriptText($html));
    file_put_contents(POST_PATH . $dateFormatted . '-' . formatTitle($title) . '.markdown', $post);
}


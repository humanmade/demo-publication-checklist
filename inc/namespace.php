<?php
/**
 * Plugin Name: Publication Checklist Items
 * Description: Demo for the Publication Checklist feature
 */

namespace Altis\Workflow\ChecklistItems;

use Altis\Workflow\PublicationChecklist as Checklist;
use Altis\Workflow\PublicationChecklist\Status;

function bootstrap() {
	// Add a caption to solve:
	add_action( 'altis.publication-checklist.register_prepublish_checks', __NAMESPACE__ . '\\register_image_texts' );

	// Checking meta:
	add_action( 'altis.publication-checklist.register_prepublish_checks', __NAMESPACE__ . '\\register_seo_title' );

	// This item is always completed:
	add_action( 'altis.publication-checklist.register_prepublish_checks', __NAMESPACE__ . '\\register_social_headline' );

	// This is optional:
	add_action( 'altis.publication-checklist.register_prepublish_checks', __NAMESPACE__ . '\\register_video' );

	// This checks tags:
	add_action( 'altis.publication-checklist.register_prepublish_checks', __NAMESPACE__ . '\\register_tags' );
}

function register_image_texts() {
	$image_block_names = [
		'core/cover',
		'core/gallery',
		'core/image',
		'core/media-text',
	];

	$check_image = function ( $image, ...$keys ) {
		$matching = array_filter( $keys, function ( $key ) use ( $image ) {
			return strlen( $image['attributes'][ $key ] ?? '' ) > 0;
		} );
		return count( $matching ) > 0;
	};

	$check_block = function ( $block ) use ( $check_image ) {
		switch ( $block['blockName'] ) {
			case 'core/cover': {
				$background_type = $block['attributes']['backgroundType'] ?? '';
				if ( $background_type !== 'image' ) {
					return true;
				}

				// As of now, the Cover block doesn't support alt texts or captions.
				return true;
			}

			case 'core/gallery': {
				$images = $block['attributes']['images'] ?? [];

				$matches = array_filter( $images, function ( $image ) use ( $check_image ) {
					return $check_image( $image, 'alt', 'caption' );
				} );
				return count( $matches ) > 0;
			}

			case 'core/image':
				$caption = preg_match( '#<figcaption>.+?</figcaption>#i', $block['innerHTML'] );
				$alt_text = preg_match( '#<img\s.*?alt="(.+?)".*?\/?>#', $block['innerHTML'] );

				return $caption || $alt_text;

			case 'core/media-text': {
				$mediaType = $block['attributes']['mediaType'];
				if ( $mediaType !== 'image' ) {
					return true;
				}

				// Only check the alt text as the Media & Text block doesn't support captions.
				return $check_image( $block, 'mediaAlt' );
			}

			default:
		}

		return false;
	};

	Checklist\register_prepublish_check( 'image-texts', [
		'type' => [
			'post',
			'page',
		],
		'run_check' => function ( array $post ) use ( $image_block_names, $check_block ) : Status {
			$blocks = parse_blocks( $post['post_content'] );
			$image_blocks = array_filter( $blocks, function ( $block ) use ( $image_block_names ) {
				return in_array( $block['blockName'], $image_block_names, true );
			} );
			$failing = array_filter( $image_blocks, function ( $value ) use ( $check_block ) {
				return $check_block( $value ) !== true;
			} );

			if ( count( $failing ) === 0 ) {
				return new Status( Status::COMPLETE, __( 'Add image alt text or caption', 'altis-demo' ) );
			}

			$block = array_values( $failing )[0];
			return new Status( Status::INCOMPLETE, __( 'Add image alt text or caption', 'altis-demo' ), $block );
		},
	] );
}

function register_seo_title() {
	Checklist\register_prepublish_check( 'seo-title', [
		'type' => [
			'post',
			'page',
		],
		'run_check' => function ( array $post, array $meta ) : Status {
			$meta_title = $meta['_yoast_wpseo_title'] ?? [];
			$status = ( count( $meta_title ) !== 1 || empty( $meta_title[0] ) ) ? Status::INCOMPLETE : Status::COMPLETE;

			return new Status( $status, 'Add a custom SEO title' );
		}
	] );
}

function register_social_headline() {
	Checklist\register_prepublish_check( 'social-headline', [
		'run_check' => function () : Status {
			return new Status( Status::COMPLETE, __( 'Adjust social headline length', 'altis-demo' ) );
		}
	] );
}

function register_video() {
	$video_block_names = [
		'core/video',
		'core-embed/videopress',
		'core-embed/vimeo',
		'core-embed/youtube',
	];

	Checklist\register_prepublish_check( 'video', [
		'run_check' => function ( array $post ) use ( $video_block_names ) : Status {
			$blocks = parse_blocks( $post['post_content'] );
			$video_blocks = array_filter( $blocks, function ( $block ) use ( $video_block_names ) {
				return in_array( $block['blockName'], $video_block_names, true );
			} );

			if ( count( $video_blocks ) > 0 ) {
				return new Status( Status::COMPLETE, __( 'Add a video to the post', 'altis-demo' ) );
			}

			return new Status( STATUS::INFO, __( 'Add a video to the post', 'altis-demo' ) );
		},
	] );
}

function register_tags() {
	Checklist\register_prepublish_check( 'tags', [
		'run_check' => function ( array $post, array $meta, array $terms ) : Status {
			if ( empty( $terms['post_tag'] ) ) {
				return new Status( Status::INCOMPLETE, __( 'Add tags to the post', 'altis-demo' ) );
			}

			return new Status( Status::COMPLETE, __( 'Add tags to the post', 'altis-demo' ) );
		}
	] );
}

<?php
namespace Podlove\Modules\SubscribeButton;

use \Podlove\Model\Podcast;
use \Podlove\Model\Feed;
use \Podlove\Cache\TemplateCache;

/**
 * Podlove Subscribe Button
 * 
 * Usage:
 * 
 *   $data = [
 *     'title'       => $podcast->title,
 *     'subtitle'    => $podcast->subtitle,
 *     'description' => $podcast->summary,
 *     'cover'       => $podcast->cover_art()->setWidth(400)->url(),
 *     'feeds'       => Button::feeds($podcast->feeds(['only_discoverable' => true])),
 *   ];
 *
 *   if ($podcast->language) {
 *     $args['language'] = Button::language($podcast->language);
 *   }
 *
 *   return (new Button())->render($data, ['size' => 'medium', 'language' => 'de']);
 */
class Button {

	private $defaults = [
		'size'     => 'big',
		'format'   => 'cover',
		'width'    => '',
		'style'    => 'filled',
		'language' => 'en',
		'color'    => '#75ad91',
		'buttonid' => NULL,
		'hide'     => false
	];

	private $args = [];

	public function render($data, $args = []) {

		$this->args = wp_parse_args($args, $this->defaults);

		// whitelist size parameter
		if (!in_array($this->args['size'], array_keys(Subscribe_Button::sizes())))
			$this->args['size'] = $this->defaults['size'];

		// whitelist style parameter
		if (!in_array($this->args['style'], array_keys(Subscribe_Button::styles())))
			$this->args['style'] = $this->defaults['style'];

		// whitelist format parameter
		if (!in_array($this->args['format'], array_keys(Subscribe_Button::formats())))
			$this->args['format'] = $this->defaults['format'];

		$this->args['data'] = $data;

		// allow args to override data
		$fields = ['title', 'subtitle', 'description', 'cover'];
		foreach ($fields as $field) {
			if (isset($this->args[$field]) && $this->args[$field]) {
				$this->args['data'][$field] = $this->args[$field];
			}
		}

		return $this->html();
	}

	public static function get_random_string() {
		if (function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes(7));
		} else {
			return dechex(mt_rand());
		}
	}

	private function module() {
		return Subscribe_Button::instance();
	}

	private function html() {

		if (!count($this->args['data']['feeds']))
			return '';

		$dataAccessor = 'podcastData' . self::get_random_string();

		$dom = new \Podlove\DomDocumentFragment;
		
		$script_data_tag = $dom->createElement('script');
		$script_data_tag->appendChild(
			$dom->createTextNode(
				sprintf("window.$dataAccessor = %s;", json_encode($this->args['data']))
			)
		);

		$use_cdn = $this->module()->get_module_option('use_cdn', true);

		if ($use_cdn) {
			$src = 'https://cdn.podlove.org/subscribe-button/javascripts/app.js';
		} else {
			$src = $this->module()->get_module_url() . '/dist/javascripts/app.js';
		}

		$script_button_tag = $dom->createElement('script');
		$script_button_tag->setAttribute('class', 'podlove-subscribe-button');
		$script_button_tag->setAttribute('src', $src);
		$script_button_tag->setAttribute('data-json-data', $dataAccessor);
		$script_button_tag->setAttribute('data-language' , self::language($this->args['language']));
		$script_button_tag->setAttribute('data-size'     , self::size($this->args['size'], $this->args['width']));
		$script_button_tag->setAttribute('data-format'   , $this->args['format']);
		$script_button_tag->setAttribute('data-style'   , $this->args['style']);
		$script_button_tag->setAttribute('data-color'   , $this->args['color']);

		if ($this->args['buttonid'])
			$script_button_tag->setAttribute('data-buttonid', $this->args['buttonid']);

		if ($this->args['hide'] && in_array($this->args['hide'], [1, '1', true, 'true', 'on']))
			$script_button_tag->setAttribute('data-hide', true);

		// ensure there is a closing script tag
		$script_button_tag->appendChild($dom->createTextNode(' '));

		$dom->appendChild($script_data_tag);
		$dom->appendChild($script_button_tag);

		return (string) $dom;
	}

	/**
	 * Feed list, ready for subscribe button.
	 * 
	 * @return array list of prepared feed data-objects
	 */
	public static function feeds($feeds) {
		return TemplateCache::get_instance()->cache_for('podlove_subscribe_button_feeds', function() use ($feeds) {

			$feeds_for_button = array_map(function($feed) {
				$file_type = $feed->episode_asset()->file_type();

				$feed_data = [
					'type'    => $file_type->type,
					'format'  => self::feed_format($file_type->extension),
					'url'     => $feed->get_subscribe_url(),
					'variant' => 'high'
				];

				$itunes_feed_id = (int) $feed->itunes_feed_id;
				if ($itunes_feed_id > 0) {
					$feed_data['directory-url-itunes'] = 'https://itunes.apple.com/podcast/id' . $itunes_feed_id;
				}

				return $feed_data;
			}, $feeds);

			return $feeds_for_button;
		});
	}
	
	/**
	 * Format string, ready for subscribe button.
	 * 
	 * @param  string $extension File extension of feed enclosures
	 * @return string
	 */
	private static function feed_format($extension) {
		switch ($extension) {
			case 'm4a': return 'aac'; break;
			case 'oga': return 'ogg'; break;
			default:
				return $extension;
			break;
		};
	}

	/**
	 * Get button compatible language string.
	 * 
	 * Examples:
	 * 
	 * 	language('de');    // => 'de'
	 *  language('de-DE'); // => 'de'
	 *  language('en-GB'); // => 'en'
	 * 
	 * @param  string $language language identifier
	 * @return string
	 */
	static function language($language) {
		return strtolower(explode('-', $language)[0]);
	}

	/**
	 * Size string, ready for subscribe button.
	 * 
	 * @param  string $size  button size identifier ('small', 'medium', 'big', 'big-logo')
	 * @param  string $width 'auto' for auto-width
	 * @return string
	 */
	private static function size($size, $width) {
		if ($width == 'auto')
			$size .= ' auto';

		return $size;
	}

}

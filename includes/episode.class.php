<?php
	class Episode
	{
		private $name;
		private $url;
		private $url_cdn;
		private $path;
		private $description;
		private $eid;

		public  $okay = TRUE;
		public	$error_code;

		const INVALID_URL_EPISODE = 101;

		public function __construct( $url, $options = array(), $parse = TRUE )
		{
			$this->url	= $url;

			if( !empty( $options['name'] ) )
			{
				$this->name = $options['name'];
			}

			if( !empty( $options['path'] ) )
			{
				$this->path	= $options['path'];
			}

			if( $parse || ( empty( $this->name ) ) )
			{
				$this->okay = $this->parse();
			}
		}

		public function get( $param )
		{
			if( isset( $this->$param ) )
			{
				return $this->$param;
			}

			return FALSE;
		}

		public function set( $param, $value = NULL )
		{
			$this->$param = $value;

			return TRUE;
		}

		public function parse()
		{
			preg_match( '~http://turbofilm.tv/Watch/([a-z0-9]+)/Season([\d]+)/Episode([\d]+)~ui', $this->get('url'), $found );

			if( empty( $found ) )
			{
				$this->error_code = self::INVALID_URL_EPISODE;
				l('EP:	INVALIDE URL EPISODE: ' . $this->get('url') . ' | ' . __LINE__ , 2 );
				return FALSE;
			}

			$res = TurboFilm::_curl( $this->get('url') );

			if( empty( $res) )
			{
				l('EP:	Empty body: '. $this->get('url') . ' | '. __LINE__, 2 );
				return FALSE;
			}

			$html = str_get_html( $res );

			$name = $html->find('.tdesc', 0);
			$name = $name->plaintext;

			if( preg_match('~Описание серии "(.*?)"~ui', $name, $f_name ) )
			{
				$name = $f_name[1] ;

				$name = $found[3] .'.'. preg_replace('~([\s]+)~', '_', $name );
				$this->set('name', html_entity_decode( $name ) );

				unset( $name );

			}
			else
			{
				l('Cant detect name of episode / '. $name );
				return FALSE;
			}

			$this->set(
				'path',
				TurboFilm::$config['download_dir'] . '/' . $found[1] . '/Season' . $found[2] . '/' . $this->get('name') .'.mp4'
			);

			if( file_exists( $this->get('path') ) )
			{
				l('EP:	File already exists: ' . $this->get('path'), 2 );

				// Что бы не кэшировать
				clearstatcache();

				if( filesize( $this->get('path') ) < 100 )
				{
					l('EP:  Filesize is broken, remove file', 2 );

					@unlink( $this->get('path') );
				}
				else
				{
					return FALSE;
				}
			}

			$this->makeUrl( $html );

			return TRUE;
		}

		private function getHtmlOfEpisode()
		{
		}

		public function makeUrl( $html )
		{
			if( empty( $html ) )
			{
				// :TODO: Разрулить, т/к/ такой ф-ции пока нету
				$html = $this->getHtmlOfEpisode();
			}

			$metadata = $html->find('#metadata', 0)->value;

			$metadata = urldecode( $metadata );

			$f = array("2", "I", "0", "=", "3", "Q", "8", "V", "7", "X", "G", "M", "R", "U", "H", "4", "1", "Z", "5", "D", "N", "6", "L", "9", "B", "W");
			$t = array("x", "u", "Y", "o", "k", "n", "g", "r", "m", "T", "w", "f", "d", "c", "e", "s", "i", "l", "y", "t", "p", "b", "z", "a", "J", "v");

			$i=0;

			while( $i < count( $f ) )
			{
				$metadata = self::_enc_replace_ab( $t[$i], $f[$i], $metadata );
				$i++;
			}

			$metadata = base64_decode( $metadata .'', TRUE );

			if( empty( $metadata ) ){ l('Cant decode metadata / '. $this->get('url') . ' / ' . __LINE__ ); }
			if( empty( $metadata ) ){ l('Cant decode metadata / '. $this->get('url') . ' / ' . __LINE__ ); }

			$metadata = str_replace('utf-16', 'utf-8', $metadata );
			$metadata = simplexml_load_string( $metadata );

			if( !self::_checkEpisodeParams( $metadata ) )
			{
				return FALSE;
			}

			$b = sha1( TurboFilm::_makeCookie() . rand(1000,9999));
			$a = sha1( $b . $metadata->eid . 'A2DC51DE0F8BC1E9' );

			$url = 'http://cdn.turbofilm.tv/' . sha1( TurboFilm::$config['language'] ) . '/' . (int)$metadata->eid . '/'
				 . ( !empty( $metadata->sources2->h1 ) ? $metadata->sources2->hq : $metadata->sources2->default )
				 . '/0/' . TurboFilm::_makeCookie() . '/' . $b . '/' . $a . '/r';

			$this->set('eid', (int)$metadata->eid );
			$this->set('url_cdn', $url );

			return TRUE;
		}

		static private function _enc_replace_ab( $e, $d, $c )
		{
			$c = str_replace($e, '___', $c);
			$c = str_replace($d, $e, $c );
			$c = str_replace('___',$d, $c );

			return $c;
		}

		static private function _checkEpisodeParams( $metadata )
		{
			$return = TRUE;

			// Не качать если хотим только hq
			if( !empty( TurboFilm::$config['only_hq'] ) && empty( $metadata->hq ) )
			{
				l('Серия не доступна в hq, пропускаем');
				$return = FALSE;
			}

			// Не качать если нету нашего языка
			if( empty( $metadata->langs->{ TurboFilm::$config['language'] } ) )
			{
				l('У серии нету нашего языка, пропускаем');
				$return = FALSE;
			}

			return $return;
		}

		public function download()
		{
			$cdn = $this->get('url_cdn');

			if( empty( $cdn ) ){ l('У серии нету урла для скачивания, omg | ' . $this->get('name') ); return FALSE; }

			l('Начинаем загрузку: ' . $this->get('name')  . ' | ' . $this->get('path') );

			$path = pathinfo( $this->get('path') ) ;

			shell_exec( TurboFilm::$config['tools']['mkdir'] . ' -p ' . $path['dirname'] );
			chmod( $path['dirname'], 0777 );

			l('Старт загрузки: ' . $this->get('url') );
			
			$from 	= array("'", '&', ";", '(', ')', '.');
			$to	= array("\'", '\&', '\;', '\(', '\)', '\.');

			exec( TurboFilm::$config['tools']['wget'] . ' --random-wait -t 100 --retry-connrefused -U="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0.1) Gecko/20100101 Firefox/8.0.1"  -O ' . str_replace($from, $to,  $this->get('path') ) .' '. escapeshellarg( $this->get('url_cdn') ), $output, $retvar );

			l('Загрузка завершенна, код wget: '. $retvar );

			if( $retvar === 0 )
			{
				// Ok

				l('[!] Считаем загрузку успешной / ' . $this->get('name') . ' / ' . $this->get('path') );

				$this->set('downloaded', TRUE );

				if( !empty( TurboFilm::$config['watch'] ) )
				{
					TurboFilm::_curl('http://turbofilm.tv/services/epwatch', array('eid' => $this->get('eid'), 'watch' => 1) );
				}
			}
			else
			{
				l('Считаем загрузку не успешной, удаляем '. $this->get('path') );
				shell_exec('rm -f ' . $this->get('path') );
			}
		}


		public function __destruct()
		{
			if( isset( $this->downloaded ) && $this->downloaded === TRUE )
			{
				if( !empty( TurboFilm::$config['email'] ) )
				{
					$mail = new PHPMailerLite();

					$mail->SetFrom( TurboFilm::$config['email'][0], 'TurboLoader');

					foreach( TurboFilm::$config['email'] as $email )
					{
						$mail->AddAddress( $email );
					}

					$mail->Subject = 'TurboLoader | ' . $this->get('name') ;

					$mail->MsgHTML('<html><p>Серия '. $this->get('url') .' закачана.</p><p>&nbsp;</p><p>'. $this->get('path') .'</p></html>');

					$mail->Send();
				}
			}
		}

	}

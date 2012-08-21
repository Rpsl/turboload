<?php
	class Episode
	{
//		private $name;
//		private $url;
//		private $url_cdn;
//		private $path;
//		private $description;
//		private $eid;

		private $data = array();

		public  $okay = TRUE;
		public	$error_code;

		const INVALID_URL_EPISODE = 101;

		public function __construct( $url, $options = array(), $parse = TRUE )
		{
			$this->url = $url;

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

		public function __get( $param )
		{
			if ( array_key_exists( $param, $this->data ) )
			{
                return $this->data[ $param ];
	        }

			return FALSE;
		}

		public function __set( $param, $value = NULL )
		{
			$this->data[ $param ] = $value;
		}

		public function __isset( $param )
	    {
	        return isset( $this->data[ $param ] );
	    }


//
//		public function get( $param )
//		{
//			if( isset( $this->$param ) )
//			{
//				return $this->$param;
//			}
//
//			return FALSE;
//		}
//
//		public function set( $param, $value = NULL )
//		{
//			$this->$param = $value;
//
//			return TRUE;
//		}

		public function parse()
		{
			preg_match( '~http://turbofilm.tv/Watch/([a-z0-9]+)/Season([\d]+)/Episode([\d]+)~ui', $this->url, $found );

			if( empty( $found ) )
			{
				$this->error_code = self::INVALID_URL_EPISODE;
				l('EP:	INVALIDE URL EPISODE: ' . $this->url . ' | ' . __LINE__ , 2 );
				return FALSE;
			}

			$res = TurboFilm::_curl( $this->url );

			if( empty( $res) )
			{
				l('EP:	Empty body: '. $this->url . ' | '. __LINE__, 2 );
				return FALSE;
			}

			$html = str_get_html( $res );

			$name = $html->find('.tdesc', 0);
			$name = $name->plaintext;

			$serial_name = $html->find('.mains a', 0);
			$serial_name = $serial_name->plaintext;

			if( preg_match('~Описание серии "(.*?)"~ui', $name, $f_name ) )
			{
				$this->name = html_entity_decode( 's'.$found[2].'e'.sprintf('%1$02d', $found[3]).' ' . $f_name[1] );
			}
			else
			{
				l('Cant detect name of episode / '. $name );
				return FALSE;
			}

			$this->path = str_replace(" ", "\\ ", TurboFilm::$config['download_dir'] . '/' . $serial_name . '/Season ' . $found[2] . '/' . $this->name .'.mp4' );

			if( file_exists( $this->path ) )
			{
				l('EP:	File already exists: ' . $this->path, 2 );

				// Что бы не кэшировать
				clearstatcache();

				if( filesize( $this->path ) < 100 )
				{
					l('EP:  Filesize is broken, remove file', 2 );

					@unlink( $this->path );
				}
				else
				{
					return FALSE;
				}
			}

			$this->makeUrl( $html );

			return TRUE;
		}

		public function makeUrl( $html )
		{
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

			if( empty( $metadata ) ){ l('Cant decode metadata / '. $this->url . ' / ' . __LINE__ ); }
			if( empty( $metadata ) ){ l('Cant decode metadata / '. $this->url . ' / ' . __LINE__ ); }

			$metadata = str_replace('utf-16', 'utf-8', $metadata );
			$metadata = simplexml_load_string( $metadata );

			if( !self::_checkEpisodeParams( $metadata ) )
			{
				return FALSE;
			}

			$b = sha1( TurboFilm::_makeCookie() . rand(1000,9999));
			$a = sha1( $b . $metadata->eid . 'A2DC51DE0F8BC1E9' );

			$this->url_cdn = 'http://cdn.turbofilm.tv/' . sha1( TurboFilm::$config['language'] ) . '/' . (int)$metadata->eid . '/'
				 . ( !empty( $metadata->sources2->h1 ) ? $metadata->sources2->hq : $metadata->sources2->default )
				 . '/0/' . TurboFilm::_makeCookie() . '/' . $b . '/' . $a . '/r';

			$this->eid = (int)$metadata->eid;

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
			$cdn = $this->url_cdn;

			if( empty( $cdn ) ){ l('У серии нету урла для скачивания, omg | ' . $this->name ); return FALSE; }

			l('Начинаем загрузку: ' . $this->name . ' | ' . $this->path );

			$path = pathinfo( $this->path ) ;

			shell_exec( TurboFilm::$config['tools']['mkdir'] . ' -p ' . $path['dirname'] );

			l('Старт загрузки: ' . $this->url );

			$from 	= array("'", '&', ";", '(', ')', '.');
			$to	= array("\'", '\&', '\;', '\(', '\)', '\.');

			exec( TurboFilm::$config['tools']['wget'] . ' --random-wait -t 100 --retry-connrefused -U="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0.1) Gecko/20100101 Firefox/8.0.1"  -O ' . str_replace($from, $to,  $this->path ) .' '. escapeshellarg( $this->url_cdn ), $output, $retvar );

			l('Загрузка завершенна, код wget: '. $retvar );

			if( $retvar === 0 )
			{
				// Ok

				l('[!] Считаем загрузку успешной / ' . $this->name . ' / ' . $this->path );

				$this->downloaded = TRUE;

				if( !empty( TurboFilm::$config['watch'] ) )
				{
					TurboFilm::_curl('http://turbofilm.tv/services/epwatch', array('eid' => $this->eid, 'watch' => 1) );
				}
			}
			else
			{
				l('Считаем загрузку не успешной, удаляем '. $this->path );
				shell_exec('rm -f ' . $this->path );
			}
		}


		public function __destruct()
		{
			if( $this->downloaded === TRUE )
			{
				if( !empty( TurboFilm::$config['email'] ) )
				{
					$mail = new PHPMailerLite();

					$mail->SetFrom( TurboFilm::$config['email'][0], 'TurboLoader');

					foreach( TurboFilm::$config['email'] as $email )
					{
						$mail->AddAddress( $email );
					}

					$mail->Subject = 'TurboLoader | ' . $this->name ;

					$mail->MsgHTML('<html><p>Серия '. $this->url .' закачана.</p><p>&nbsp;</p><p>'. $this->path .'</p></html>');

					$mail->Send();
				}
			}
		}
	}

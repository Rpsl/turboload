<?php

    /**
     * Episode
     *
     * Обеспечивает взаимодействие на уровне одной серии.
     * - Парсинг данных
     * - Генерация урла
     * - Скачивание серии
     * - Уведомление по email
     *
     * Example:
     *
     *      $ep = new Episode('https://turbofilm.tv/Watch/BreakingBad/Season2/Episode1');
     *      $ep->download();
     *      unset( $ep );
     *
     */
    class Episode
    {
        private $serial_name;
        private $data = array();

        private $emailed = FALSE;
        private $downloaded = FALSE;

        private $episode_html_page;
        private $episode_name;

        private $url_cdn;
        private $eid;


        /**
         * Символы которые будут вырезаны из названия серии.
         *
         * @var array
         */
        private $replace_in_name    = array(':', '/');
        private $replace_in_name_to = array('', ' ');

        public function __construct( $url )
        {
            $res = preg_match( '~https://turbofilm.tv/Watch/([a-z0-9]+)/Season([\d]+)/Episode([\d]+)~ui', $url, $found );

            if( empty( $res ) )
            {
                l( 'EP:INVALIDE URL EPISODE: ' . $url . ' | ' . __LINE__ );
                throw new Exception('');
            }

            $this->url = $url;

            $this->parse();
        }

//        public function __get( $param )
//        {
//            if( array_key_exists( $param, $this->data ) )
//            {
//                return $this->data[ $param ];
//            }
//
//            return FALSE;
//        }
//
//        public function __set( $param, $value = NULL )
//        {
//            $this->data[ $param ] = $value;
//        }
//
//        public function __isset( $param )
//        {
//            return isset( $this->data[ $param ] );
//        }

        // говнометоды, юзаются в turbofilm.class.php
        public function getUrl()
        {
            return $this->url_cdn;
        }

        // говнометоды, юзаются в turbofilm.class.php
        public function getEid()
        {
            return $this->eid;
        }

        public function getName()
        {
            return $this->episode_name;
        }

        public function parse()
        {

            $this->episode_html_page = TurboFilm::_curl( $this->url );

            if( empty( $this->episode_html_page ) )
            {
                l( 'EP:    Empty body: ' . $this->url . ' | ' . __LINE__);

                return FALSE;
            }

            if( strstr( $this->episode_html_page, '<title>Привет, я Турбофильм!</title>' ) )
            {
                l('EP: Serial removed, use proxy Luke.');
                return FALSE;
            }



            $this->getEpisodeName();
            $this->getSerialName();
            $this->getPath();

            if( file_exists( $this->getPath() ) )
            {
                l( 'EP: File already exists, remove ' . $this->getPath() );

                @unlink( $this->getPath() );

                // Что бы не кэшировать
                clearstatcache();
            }

            $this->makeUrl();

            return TRUE;
        }

        private function getEpisodeName()
        {
            $html = str_get_html( $this->episode_html_page );

            $name = $html->find( 'title', 0 );
            $name = $name->plaintext;
            $name = explode('—', $name );
            $name = trim( $name[0] );

            $prefix = $this->getEpisodePrefix();

            $name = $prefix .' '. $name;

            $name = html_entity_decode( $name );
            $name = strip_tags( $name );
            $name = str_replace( $this->replace_in_name, $this->replace_in_name_to, $name );

            $this->episode_name = $name;

//            throw new Exception('');
        }

        private function getEpisodePrefix()
        {
            return 's'.$this->getSeasonNumber().'e'.$this->getEpisodeNumber();
        }

        private function getSeasonNumber()
        {
            preg_match( '~https://turbofilm.tv/Watch/([a-z0-9]+)/Season([\d]+)/Episode([\d]+)~ui', $this->url, $found );

            return $found[2];
        }

        private function getEpisodeNumber()
        {
            preg_match( '~https://turbofilm.tv/Watch/([a-z0-9]+)/Season([\d]+)/Episode([\d]+)~ui', $this->url, $found );

            return sprintf( '%1$02d', $found[3] );
        }

        private function getSerialName()
        {
            $html = str_get_html( $this->episode_html_page );
            $this->serial_name = $html->find( '.mains a.en', 0 )->plaintext;

//            throw new Exception('');
        }

        private function getPath()
        {
            $path =
                TurboFilm::$config[ 'download_dir' ] .'/'.
                $this->serial_name
                .'/Season '.
                $this->getSeasonNumber() .'/'.
                $this->episode_name . '.mp4';

            return $path;
        }


        public function makeUrl()
        {
            $html = str_get_html( $this->episode_html_page );

            $metadata = $html->find( '#metadata', 0 )->value;

            $metadata = urldecode( $metadata );

            $f = array( "2", "I", "0", "=", "3", "Q", "8", "V", "7", "X", "G", "M", "R", "U", "H", "4", "1", "Z", "5", "D", "N", "6", "L", "9", "B", "W" );
            $t = array( "x", "u", "Y", "o", "k", "n", "g", "r", "m", "T", "w", "f", "d", "c", "e", "s", "i", "l", "y", "t", "p", "b", "z", "a", "J", "v" );

            $i = 0;

            while( $i < count( $f ) )
            {
                $metadata = self::_enc_replace_ab( $t[ $i ], $f[ $i ], $metadata );
                $i++;
            }

            $metadata = base64_decode( $metadata . '', TRUE );

            if( empty( $metadata ) )
            {
                l( 'cant decode metadata / ' . $this->url . ' / ' . __LINE__ );
            }

            $metadata = str_replace( 'utf-16', 'utf-8', $metadata );
            $metadata = simplexml_load_string( $metadata );

            if( !self::_checkEpisodeParams( $metadata ) )
            {
                return FALSE;
            }

            $b = sha1( TurboFilm::_makeCookie() . rand( 1000, 9999 ) );
            $a = sha1( $b . $metadata->eid . 'A2DC51DE0F8BC1E9' );

            $this->url_cdn = 'https://cdn.turbofilm.tv/' . sha1( TurboFilm::$config[ 'language' ] ) . '/' . (int)$metadata->eid . '/' . ( !empty( $metadata->sources2->hq ) ? $metadata->sources2->hq : $metadata->sources2->default ) . '/0/' . TurboFilm::_makeCookie() . '/' . $b . '/' . $a . '/r';

            $this->eid = (int)$metadata->eid;

            return TRUE;
        }

        static private function _enc_replace_ab( $e, $d, $c )
        {
            $c = str_replace( $e, '___', $c );
            $c = str_replace( $d, $e, $c );
            $c = str_replace( '___', $d, $c );

            return $c;
        }

        /**
         * @static
         *
         * Проверка параметров данной серии.
         * - Язык
         * - Качество
         *
         * @param $metadata
         *
         * @return bool
         */
        static private function _checkEpisodeParams( $metadata )
        {
            $return = TRUE;

            // Не качать если хотим только hq
            if( !empty( TurboFilm::$config[ 'only_hq' ] ) && empty( $metadata->hq ) )
            {
                l( 'dont have HQ quality. Skipped.' );
                $return = FALSE;
            }

            // Не качать если нету нашего языка
            if( empty( $metadata->langs->{TurboFilm::$config[ 'language' ]} ) )
            {
                l( 'dont have language ' );
                $return = FALSE;
            }

            return $return;
        }

        /**
         * Скачивание серии.
         *
         * Скачивание происходит с помощью wget, т.к. это самый
         * простой способ поддерживать переходы и докачку файла при обрыве потока.
         *
         * Проверяется код ответа wget и только если он успешный (int) 0 серия считается скачаной.
         * Если код ответа отличается, то попытка считается не успешной и файл удаляется.
         *
         * Если серия скачалась и в конфиге есть соответсвующая настройка, то серия отмечается как просмотренная.
         *
         */
        public function download()
        {
            // Сюда нужно запилить проверку, что все данные есть и серию можно скачивать.

            l( 'start downloading: ' . $this->episode_name . ' | ' . $this->getPath() );

            $path = pathinfo( $this->getPath() );

            shell_exec( 'mkdir -p ' . escapeshellarg( $path[ 'dirname' ] ) );

            l( 'url: ' . $this->url );

            exec( TurboFilm::$config[ 'tools' ][ 'wget' ] . ' --no-check-certificate --random-wait -t 10 --retry-connrefused -U="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0.1) Gecko/20100101 Firefox/8.0.1"  -O ' . escapeshellarg( $this->getPath() ) . ' ' . escapeshellarg( $this->url_cdn ), $output, $retvar );

            l( 'downloading finished, wget exit code: ' . $retvar );

            if( $retvar === 0 )
            {
                l( '[!] downloading is ok / ' . $this->episode_name . ' / ' . $this->getPath() );

                $this->downloaded = TRUE;

                TurboFilm::_curl( 'https://turbofilm.tv/services/epwatch', array( 'eid' => $this->eid, 'watch' => 1 ) );
            }
            else
            {
                l( 'downloading is broken, removed ' . $this->getPath() );
                shell_exec( 'rm -f ' . $this->getPath() );
            }
        }

        /**
         * Проверяем скачался ли эпизод и если необходимо, то отправляем уведомление по email
         */
        public function sendEmail()
        {
            // Не будем отправлять уведомления для одной серии больше чем 1 раз
            if( $this->emailed === TRUE )
            {
                return FALSE;
            }

            if( $this->downloaded === TRUE && !empty( TurboFilm::$config[ 'email' ] ) )
            {
                try
                {
                    $data = array(
                        'from'    => 'Turbofilm downloader <turboload@'. TurboFilm::$config['mailgun']['domain'].'>',
                        'to'      => implode(', ', TurboFilm::$config['email']),
                        'subject' => 'TurboLoader | ' . $this->serial_name . ' | ' . $this->episode_name,
                        'text'    => 'Серия ' . $this->url . ' закачана.',
                        'html'    => '<html><p>Серия ' . $this->url . ' закачана.</p><p>&nbsp;</p><p>' . $this->getPath() . '</p></html>',
                    );

                    $data = http_build_query( $data );

                    $opts = array(
                        'http' =>   array(
                            'method' => 'POST',
                            'header' =>
                            "Content-type: application/x-www-form-urlencoded\r\n".
                            "Content-Length: " . strlen($data) . "\r\n".
                            "Authorization: Basic " . base64_encode( TurboFilm::$config['mailgun']['api-key'] ) . "\r\n",
                            'content' => $data
                        )
                    );

                    $stream = stream_context_create( $opts );

                    file_get_contents('https://api.mailgun.net/v2/'.TurboFilm::$config['mailgun']['domain'].'/messages', false, $stream );

                    $this->emailed = TRUE;

                }
                catch( Exception $e )
                {
                    $this->emailed = FALSE;
                    return FALSE;
                }

                return TRUE;
            }

            return FALSE;
        }

        /**
         * При умирании, дернем отправку уведомлений, что бы не делать это руками.
         */
        public function __destruct()
        {
            $this->sendEmail();
        }
    }

<?php

	/**
	 * Очередь заданий.
	 */
	class Etask
	{
		private $task = array();

		static private $instance = NULL;

		/**
		 * @static
		 * @return Etask
		 */
		static public function getInstance()
		{
			if( self::$instance === NULL )
			{
				self::$instance = new Etask();
			}

			return self::$instance;
		}

		/**
		 * @param Object Episode $object
		 *
		 * Добавить эпизод в очередь.
		 *
		 * @return bool
		 */
		public function addEpisode( $object )
		{
			if( $this->ok() )
			{
				if( $object instanceof Episode )
				{
					$this->task[] = $object;

					return TRUE;
				}
			}

			return FALSE;
		}

		/**
		 * Получение эпизода из очереди
		 *
		 * @return Episode
		 */
		public function getEpisode()
		{
			$ep = array_shift( $this->task );

			if( empty( $ep ) )
			{
				return FALSE;
			}

			return $ep;
		}

		/**
		 * Проверка что очередь не переполнена.
		 * @return bool
		 */
		public function ok()
		{
			return ( count( $this->task ) < TurboFilm::$config['tasks'] );
		}

		/**
		 * @static
		 *
		 * Запуск скачивания заданий.
		 */
		static public function download_tasks()
		{
			$task = Etask::getInstance();

			while( $ep = $task->getEpisode() )
			{
				/**
				 * @var $ep Episode
				 */
				$ep->download();
			}
		}

	}

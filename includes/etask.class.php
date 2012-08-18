<?php

	class Etask
	{
		private $task;
		static private $instance = NULL;

		public function __construct()
		{
			$this->task = array();
		}

		static public function getInstance()
		{
			if( self::$instance === NULL )
			{
				self::$instance = new Etask();
			}

			return self::$instance;
		}

		public function addEpisode( $object )
		{
			if( $object instanceof Episode )
			{
				$this->task[] = $object;

				return TRUE;
			}

			return FALSE;
		}

		public function getEpisode()
		{
			$ep = array_shift( $this->task );

			if( empty( $ep ) )
			{
				return FALSE;
			}

			return $ep;
		}

		public function ok()
		{
			return ( count( $this->task ) < TurboFilm::$config['tasks'] );
		}

		static public function download_tasks()
		{
			$task = Etask::getInstance();

			while( $ep = $task->getEpisode() )
			{
				$ep->download();
			}
		}

	}

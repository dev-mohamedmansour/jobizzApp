<?php

namespace App;
	  
	  enum DocumentType: string
	  {
			 case CV = 'cv';
			 case PORTFOLIO = 'portfolio';
			 case CERTIFICATE = 'certificate';
			 case OTHER = 'other';
	  }

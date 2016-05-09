<?php

$GLOBALS['TL_DCA']['tl_content']['config']['onsubmit_callback'][] = array('tl_content_sitemap','generateImageSitemap');

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['addImage'] = str_replace('floating', 'floating,sitemap', $GLOBALS['TL_DCA']['tl_content']['subpalettes']['addImage']);
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['useImage'] = str_replace('caption', 'caption,sitemap', $GLOBALS['TL_DCA']['tl_content']['subpalettes']['useImage']);
$GLOBALS['TL_DCA']['tl_content']['palettes']['image'] = str_replace('caption;', 'caption;sitemap;', $GLOBALS['TL_DCA']['tl_content']['palettes']['image']);

$GLOBALS['TL_DCA']['tl_content']['fields']['sitemap'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_content']['sitemap'],
	'exclude'                 => true,
	'inputType'               => 'checkbox',
	'eval' => array('tl_class'=>'clr'),
	'sql'                     => "char(1) NOT NULL default ''"
);

class tl_content_sitemap extends tl_content {

	public function generateImageSitemap($dc)
	{
		$type = $dc->activeRecord->type;
		\Automator::purgeXmlFiles();
		if($type == "text" || $type == "image")
		{
			$pages = array();
			$objDatabase = \Database::getInstance();

			//on parcours tous les content elements qui ont la case "sitemap" de cochée
			$objContents = $objDatabase->prepare("SELECT * FROM tl_content WHERE sitemap = ?")
			->execute(1);
			
			while($objContents->next())
			{
				//pour chaque on recupere les infos du fichier ($path)
				$objImage = \FilesModel::findById($objContents->singleSRC);
				$arrImageInfos = array
				(
					'path'=> \Environment::get('url')."/".$objImage->path,
					'caption'=> $objContents->caption,
					'title'=> ($objContents->title != '')?$objContents->title : $objContents->alt
				);
				//on recupere l'url de la page sur laquelle l'image se trouve
				$objPage = $this->getPage($objContents->pid);
				
				$pages[$objPage->id]['url'] = rawurlencode(\Environment::get('url')."/".$this->generateFrontendUrl($objPage->row()));
				$pages[$objPage->id]['url'] = str_replace(array('%2F', '%3F', '%3D', '%26', '%3A//'), array('/', '?', '=', '&', '://'), $pages[$objPage->id]['url']);
				$pages[$objPage->id]['url'] = ampersand($pages[$objPage->id]['url'], true);

				$pages[$objPage->id]['images'][] = $arrImageInfos;
				
			}

			$objFile = new \File('share/sitemap-images.xml',true);

			$objFile->truncate();
			$objFile->append('<?xml version="1.0" encoding="UTF-8"?>');
			$objFile->append('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">');

			foreach($pages as $page)
			{
				$objFile->append('<url>');
				$objFile->append('<loc>'.$page['url'].'</loc>');
				foreach($page['images'] as $img){

					$objFile->append('<image:image>');
					$objFile->append('<image:loc>'.$img['path'].'</image:loc>');
					if(!empty($img['caption']))
						$objFile->append('<image:caption>'.$img['caption'].'</image:caption>');
					if(!empty($img['title']))
						$objFile->append('<image:title>'.$img['title'].'</image:title>');
					$objFile->append('</image:image>');
				}
				$objFile->append('</url>');
			}
			$objFile->append('</urlset>');

			$objFile->close();
			$this->log('Generated Image sitemap "sitemap-images.xml"', __METHOD__, TL_CRON);
		}
	}

	public function getPage($content_pid)
	{
		$objArticle = \ArticleModel::findPublishedById($content_pid);
		
		if($objArticle != null)
		{
			$objPage = \PageModel::findPublishedById($objArticle->pid);

			if($objPage != null)
					return $objPage;
		}
		return;
	}
}
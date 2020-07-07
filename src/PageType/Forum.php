<?php
/*
 * This file is part of contao-phpbbBridge
 * 
 * Copyright (c) 2015-2016 Daniel Schwiperich
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 */

namespace Ctsmedia\Phpbb\BridgeBundle\PageType;

use Contao\FrontendTemplate;
use Contao\PageRegular;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forum PageType for Contao
 *
 * @package Ctsmedia\Phpbb\BridgeBundle\PageType
 * @author Daniel Schwiperich <https://github.com/DanielSchwiperich>
 */
class Forum extends PageRegular
{
    /**
     * Return a response object
     *
     * The forum Page usually does not get called because the frontend listener
     * overrides the url for navigation for example. When called directly the layout parts
     * phpBB gets created und pushed to phpbb
     *
     * @see ContaoFrontendListener
     *
     * @param \PageModel $objPage
     * @param boolean $blnCheckRequest
     *
     * @return Response
     */
    public function getResponse($objPage, $blnCheckRequest = false)
    {
        $this->prepare($objPage);

        // prepare the template contents
        $this->Template->main = "%%FORUM%%";

        $style = $this->prepareHeadTags($this->Template->stylesheets);
        $mooScripts = $this->prepareHeadTags($this->Template->mooScripts);
        $framework = $this->prepareHeadTags($this->Template->framework);
        $head = $this->prepareHeadTags($this->Template->head);
        $this->Template->head = "";

        $response = $this->Template->getResponse($blnCheckRequest);

        // layout sections
        $overall_header = '';
        $overall_footer = '';
        $sections = $this->generateLayoutSections($response->getContent());


        // template vars will be replaced with dynamic content on each request
        $overall_header = '{CONTAO_LAYOUT_HEADER}';
        $overall_footer = '{CONTAO_LAYOUT_FOOTER}';


        // If dynamic generation is set and json format requested we can return and leave (no need to generate files)
        if ($this->Input->get('format') == 'json') {
            return new JsonResponse($sections);
        }

        // Generate files for static and generic contents
        $phpbbHeaders = "";
//        $phpbbHeaders .= '<link rel="stylesheet" type="text/css" media="all" href="/assets/css/mate.scss-0011b7b9.css">';
        $phpbbHeaders .= $framework;
        $phpbbHeaders .= $style;
        $phpbbHeaders .= $mooScripts;
        $phpbbHeaders .= $head;

        file_put_contents(__DIR__ . '/../Resources/phpBB/ctsmedia/contaophpbbbridge/styles/all/template/event/overall_header_stylesheets_after.html',
            $phpbbHeaders);
        file_put_contents(__DIR__ . '/../Resources/phpBB/ctsmedia/contaophpbbbridge/styles/all/template/event/simple_header_stylesheets_after.html',
            $phpbbHeaders);
        file_put_contents(__DIR__ . '/../Resources/phpBB/ctsmedia/contaophpbbbridge/styles/all/template/event/overall_header_body_before.html',
            $overall_header);
        file_put_contents(__DIR__ . '/../Resources/phpBB/ctsmedia/contaophpbbbridge/styles/all/template/event/overall_footer_after.html',
            $overall_footer);

        System::getContainer()->get('phpbb_bridge.connector')->updateConfig(array('contao.body_class' => $this->Template->class));

        return $response;
    }

    /**
     * Split and adjusts the content into layout sections to use for phpbb template events
     *
     * @param $html
     * @return array layout sections
     */
    protected function generateLayoutSections($html) {

        // Ajust link paths
        $html = preg_replace('/href\=\"(?!http|\/)/', 'href="/', $html);

        // Ajust src paths
        $html = preg_replace('/src\=\"(?!http|\/)/', 'src="/', $html);
        $html = preg_replace('/content\=\"(?!http|\/)/', 'content="/', $html);
        $html = preg_replace('/url\(\"(?!http|\/)/', 'url("/', $html);
        $html = preg_replace('/url\(\'(?!http|\/)/', 'url(\'/', $html);


		// extract head part, it may contain styles or js
		$head = '';
		$fullhead = '';
		if(preg_match('/(?s)<head>(.*?)<\/head>/', $html, $match) == 1) {
			$fullhead =  $match[1];

			// from head we need only scripts and styles, which we shall inject
			// in html body before header
			
			// extract styles; following are 2 examples of lines to match
			// <link rel="stylesheet" type="text/css" media="all" href="/assets/css/mate.scss-0011b7b9.css">
			// <link rel="stylesheet" href="/assets/css/layout.min.css,responsive.min.css,colorbox.min.css,mediaelementp...-3aef9328.css">    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
			if(preg_match_all('/(?s)<link[ ]?rel[ ]*=[ ]*"stylesheet"(.*?)>/', $fullhead, $match)) {
				for($i = 0; $i < count($match[1]); $i++) {
					$head .= '<link rel="stylesheet"' . $match[1][$i] . ">\n";
				}
			}
			
			// extract scripts; following are 2 examples of lines to match
			// <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
			// <script>
			//     jQuery(function($) {
			//     .......
			// </script>
			if(preg_match_all('/(?s)<script[ ]*(.*?)<\/script>/', $fullhead, $match)) {
				for($i = 0; $i < count($match[1]); $i++) {
					$head .= '<script ' . $match[1][$i] . "</script>\n";
				}
			}
		}
		
		// get just the body part
		// things are complicated by embedded <esi:include :
		// <body id="top" class="<esi:include src="/_fragment?_path=insertTag%3D%257B%257Bua%253A%253Aclass%257D%257D%26_format%3Dhtml%26_locale%3Dit%26_controller%3Dcontao.controller.insert_tags%253ArenderAction&clientCache=0&pageId=160&request=phpbb_bridge%2Flayout&_hash=X7%2FjWKP3UxQ%2B4EwPShHMphDHtgEKyV0UvVLnlrLwXkM%3D" onerror="continue" /> default-col" itemscope itemtype="http://schema.org/WebPage">
		$overall_body = '';
		if (preg_match('/(?s)<body[^<]*<esi:include[^>]*>[^>]*>(.*?)<\/body>/', $html, $match)) {
			$overall_body =  $match[1];
		}
		else if(preg_match('/(?s)<body[^>]*>(.*?)<\/body>/', $html, $match)) {
			$overall_body =  $match[1];
		}

		// split body around forum content marker
        $parts = explode("%%FORUM%%", $overall_body);
        $overall_header = $parts[0];
        $overall_footer = $parts[1];

		// inject head scripts and styles to header
		$overall_header = $head . "\n" . $overall_header;
		
        return array(
            'overall_header' => $overall_header,
            'overall_footer' => $overall_footer,
        );
    }

    /**
     * Adjust urls, paths and replaces inserttags
     *
     * @param FrontendTemplate $tpl
     * @param $html
     * @return string
     */
    protected function prepareHeadTags($html) {
        $html = $this->Template->replaceInsertTags($html);
        $html = $this->Template->replaceDynamicScriptTags($html);
        $html = preg_replace('/src\=\"(?!http|\/)/', 'src="/', $html);
        $html = preg_replace('/href\=\"(?!http|\/)/', 'href="/', $html);
        $html = preg_replace('/url\(\"(?!http|\/)/', 'url("/', $html);
        $html = preg_replace('/url\(\'(?!http|\/)/', 'url(\'/', $html);

        //dump($html);
        
        return $html;
    }

}

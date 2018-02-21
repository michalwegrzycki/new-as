<?php

return <<<'VALUE'
"namespace IPS\\Theme;\nclass class_cms_front_widgets extends \\IPS\\Theme\\Template\n{\n\tpublic $cache_key = '';\n\tfunction Blocks( $content ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n{$content}\n\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction Categories( $url ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n\nCONTENT;\n\n$catClass = '\\IPS\\cms\\Categories' . \\IPS\\cms\\Databases\\Dispatcher::i()->databaseId;\n$return .= <<<CONTENT\n\n\nCONTENT;\n\n$categories = $catClass::roots();\n$return .= <<<CONTENT\n\n\nCONTENT;\n\nif ( !empty( $categories ) ):\n$return .= <<<CONTENT\n\n\t<h3 class='ipsWidget_title ipsType_reset'>\nCONTENT;\n\n$return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'categories', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), TRUE, array(  ) );\n$return .= <<<CONTENT\n<\/h3>\n\t<div class='ipsSideMenu ipsAreaBackground_reset ipsPad_half'>\n\t\t<ul class='ipsSideMenu_list'>\n\t\t\t\nCONTENT;\n\nforeach ( $categories as $category ):\n$return .= <<<CONTENT\n\n\t\t\t\t<li>\n\t\t\t\t\t<a href=\"\nCONTENT;\n$return .= htmlspecialchars( $category->url(), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n\" class='ipsSideMenu_item ipsTruncate ipsTruncate_line'><span class='ipsBadge ipsBadge_style1 ipsPos_right'>\nCONTENT;\n\n$return .= htmlspecialchars( \\IPS\\cms\\Records::contentCount( $category ), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/span><strong class='ipsType_normal'>\nCONTENT;\n$return .= htmlspecialchars( $category->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/strong><\/a>\n\t\t\t\t\t\nCONTENT;\n\nif ( $category->hasChildren() ):\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t<ul class=\"ipsSideMenu_list\">\n\t\t\t\t\t\t\t\nCONTENT;\n\n$counter = 0;\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\nCONTENT;\n\nforeach ( $category->children() as $idx => $subcategory ):\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\nCONTENT;\n\n$counter++;\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\t<li>\n\t\t\t\t\t\t\t\t\t\nCONTENT;\n\nif ( $counter >= 5 ):\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\t\t\t<a href='\nCONTENT;\n$return .= htmlspecialchars( $category->url(), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n' class='ipsSideMenu_item'><span class='ipsType_light ipsType_small'>\nCONTENT;\n\n$pluralize = array( count( $category->children() ) - 4 ); $return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'and_x_more', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), FALSE, array( 'pluralize' => $pluralize ) );\n$return .= <<<CONTENT\n<\/span><\/a>\n\t\t\t\t\t\t\t\t\t\t\nCONTENT;\n\nbreak;\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\t\t\nCONTENT;\n\nelse:\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\t\t\t<a href=\"\nCONTENT;\n$return .= htmlspecialchars( $subcategory->url(), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n\" class='ipsSideMenu_item ipsTruncate ipsTruncate_line'><strong class='ipsPos_right ipsType_small'>\nCONTENT;\n\n$return .= htmlspecialchars( \\IPS\\cms\\Records::contentCount( $subcategory ), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/strong>\nCONTENT;\n$return .= htmlspecialchars( $subcategory->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/a>\n\t\t\t\t\t\t\t\t\t\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t\t\t<\/li>\n\t\t\t\t\t\t\t\nCONTENT;\n\nendforeach;\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t<\/ul>\n\t\t\t\t\t\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n\t\t\t\t<\/li>\n\t\t\t\nCONTENT;\n\nendforeach;\n$return .= <<<CONTENT\n\n\t\t<\/ul>\n\t\t<p class='ipsType_center'>\n\t\t\t<a href='\nCONTENT;\n$return .= htmlspecialchars( $url->setQueryString('show','categories'), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n' class=''>\nCONTENT;\n\n$return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'cms_show_categories', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), TRUE, array(  ) );\n$return .= <<<CONTENT\n &nbsp;<i class='fa fa-caret-right'><\/i><\/a>\n\t\t<\/p>\n\t<\/div>\n\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction Database( $database ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n\nCONTENT;\n\n$return .= \\IPS\\cms\\Databases\\Dispatcher::i()->setDatabase( \"$database->id\" )->run();\n$return .= <<<CONTENT\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction DatabaseFilters( $database, $category, $form, $orientation='vertical' ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n<h3 class='ipsWidget_title ipsType_reset'>\nCONTENT;\n\n$sprintf = array($category->_title); $return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'block_DatabaseFilters_title', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), FALSE, array( 'sprintf' => $sprintf ) );\n$return .= <<<CONTENT\n<\/h3>\n<div class='ipsWidget_inner ipsPad'>\n\t{$form}\n<\/div>\n\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction Editor( $content, $orientation='horizontal' ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n<div class='ipsWidget_inner \nCONTENT;\n\nif ( $orientation == 'vertical' ):\n$return .= <<<CONTENT\nipsPad\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n'>\n\t{$content}\n<\/div>\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction RecordFeed( $records, $title, $orientation='vertical' ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n\nCONTENT;\n\nif ( !empty( $records )  ):\n$return .= <<<CONTENT\n\n\t<h3 class='ipsWidget_title ipsType_reset'>\nCONTENT;\n$return .= htmlspecialchars( $title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h3>\n\t\nCONTENT;\n\nif ( $orientation == 'vertical' ):\n$return .= <<<CONTENT\n\n\t\t<div class='ipsPad_half ipsWidget_inner'>\n\t\t\t<ul class='ipsDataList ipsDataList_reducedSpacing ipsContained_container'>\n\t\t\t\t\nCONTENT;\n\nforeach ( $records as $record ):\n$return .= <<<CONTENT\n\n\t\t\t\t\t<li class='ipsDataItem'>\n\t\t\t\t\t\t<div class='ipsDataItem_icon ipsPos_top'>\n\t\t\t\t\t\t\t\nCONTENT;\n\n$return .= \\IPS\\Theme::i()->getTemplate( \"global\", \"core\" )->userPhoto( $record->author(), 'tiny' );\n$return .= <<<CONTENT\n\n\t\t\t\t\t\t<\/div>\n\t\t\t\t\t\t<div class='ipsDataItem_main cWidgetComments'>\n\t\t\t\t\t\t\t<div class=\"ipsCommentCount ipsPos_right \nCONTENT;\n\nif ( ( $record->record_comments ) === 0 ):\n$return .= <<<CONTENT\nipsFaded\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\" data-ipsTooltip title='\nCONTENT;\n\n$pluralize = array( $record->record_comments ); $return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'num_replies', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), FALSE, array( 'pluralize' => $pluralize ) );\n$return .= <<<CONTENT\n'>\nCONTENT;\n\n$return .= htmlspecialchars( $record->record_comments, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/div>\n\t\t\t\t\t\t\t<div class='ipsType_break ipsContained'>\n\t\t\t\t\t\t\t\t<a href=\"\nCONTENT;\n$return .= htmlspecialchars( $record->url()->setQueryString( 'do', 'getLastComment' ), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n\" title='\nCONTENT;\n\n$sprintf = array(\\IPS\\Member::loggedIn()->language()->addToStack( 'content_db_lang_sl_' . $record::$customDatabaseId, FALSE ), $record->_title); $return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'view_this_cmsrecord', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), FALSE, array( 'sprintf' => $sprintf ) );\n$return .= <<<CONTENT\n' class='ipsDataItem_title'>\nCONTENT;\n$return .= htmlspecialchars( $record->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/a>\n\t\t\t\t\t\t\t<\/div>\n\t\t\t\t\t\t\t<p class='ipsType_reset ipsType_medium ipsType_blendLinks'>\n\t\t\t\t\t\t\t\t<span>\nCONTENT;\n\n$htmlsprintf = array($record->author()->link()); $return .= \\IPS\\Member::loggedIn()->language()->addToStack( htmlspecialchars( 'byline_nodate', \\IPS\\HTMLENTITIES, 'UTF-8', FALSE ), FALSE, array( 'htmlsprintf' => $htmlsprintf ) );\n$return .= <<<CONTENT\n<\/span><br>\n\t\t\t\t\t\t\t\t<span class=\"ipsType_light\">\nCONTENT;\n\n$val = ( $record->mapped('date') instanceof \\IPS\\DateTime ) ? $record->mapped('date') : \\IPS\\DateTime::ts( $record->mapped('date') );$return .= $val->html();\n$return .= <<<CONTENT\n<\/span>\n\t\t\t\t\t\t\t<\/p>\n\t\t\t\t\t\t<\/div>\n\t\t\t\t\t<\/li>\n\t\t\t\t\nCONTENT;\n\nendforeach;\n$return .= <<<CONTENT\n\n\t\t\t<\/ul>\n\t\t<\/div>\n\t\nCONTENT;\n\nelse:\n$return .= <<<CONTENT\n\n\t\t<div class='ipsWidget_inner'>\n\t\t\t<ul class='ipsDataList ipsContained_container'>\n\t\t\t\t\nCONTENT;\n\n$return .= \\IPS\\cms\\Theme::i()->getTemplate( \"listing\", \"cms\", 'database' )->recordRow( null, null, $records );\n$return .= <<<CONTENT\n\n\t\t\t<\/ul>\n\t\t<\/div>\n\t\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction Rss( $items, $title, $orientation='vertical' ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n\nCONTENT;\n\nif ( !empty( $items )  ):\n$return .= <<<CONTENT\n\n\t<h3 class='ipsWidget_title ipsType_reset'>\nCONTENT;\n$return .= htmlspecialchars( $title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h3>\n\t\t<div class='ipsPad_half ipsWidget_inner'>\n\t\t\t<ul class='ipsDataList ipsDataList_reducedSpacing'>\n\t\t\t\t\nCONTENT;\n\nforeach ( $items as $item ):\n$return .= <<<CONTENT\n\n\t\t\t\t\t<li class='ipsDataItem'>\n\t\t\t\t\t\t<div class='ipsDataItem_main'>\n\t\t\t\t\t\t\t<div class='ipsType_break ipsContained'><a href=\"\nCONTENT;\n$return .= htmlspecialchars( $item['link'], ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n\" target=\"_blank\" rel=\"noopener\" class='ipsTruncate ipsTruncate_line'>\nCONTENT;\n$return .= htmlspecialchars( $item['title'], ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/a><\/div>\n\t\t\t\t\t\t\t<span class='ipsType_light ipsType_small'>\nCONTENT;\n\n$val = ( $item['date'] instanceof \\IPS\\DateTime ) ? $item['date'] : \\IPS\\DateTime::ts( $item['date'] );$return .= $val->html();\n$return .= <<<CONTENT\n<\/span>\n\t\t\t\t\t\t<\/div>\n\t\t\t\t\t<\/li>\n\t\t\t\t\nCONTENT;\n\nendforeach;\n$return .= <<<CONTENT\n\n\t\t\t<\/ul>\n\t\t<\/div>\n\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction Wysiwyg( $content, $orientation='horizontal' ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n<div class='ipsWidget_inner ipsPad ipsType_richText'>\n\t{$content}\n<\/div>\n\nCONTENT;\n\n\t\treturn $return;\n}}"
VALUE;

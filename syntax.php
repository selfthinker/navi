<?php
/**
 * Build a navigation menu from a list
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_navi extends DokuWiki_Syntax_Plugin {

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'gohr@cosmocode.de',
            'date'   => '2008-08-01',
            'name'   => 'Navigation Plugin',
            'desc'   => 'Build a navigation menu from a list',
            'url'    => 'http://wiki.splitbrain.org/plugin:navi',
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 155;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{navi>[^}]+}}',$mode,'plugin_navi');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        $id = cleanID(substr($match,7,-2));

        // fetch the instructions of the control page
        $instructions = p_cached_instructions(wikiFN($id),false,$id);

        // prepare some vars
        $max = count($instructions);
        $pre = true;
        $lvl = 0;
        $parents = array();
        $page = '';
        $cnt  = 0;

        // build a lookup table
        for($i=0; $i<$max; $i++){
            if($instructions[$i][0] == 'listu_open'){
                $pre = false;
                $lvl++;
                if($page) array_push($parents,$page);
            }elseif($instructions[$i][0] == 'listu_close'){
                $lvl--;
                array_pop($parents);
            }elseif($pre || $lvl == 0){
                unset($instructions[$i]);
            }elseif($instructions[$i][0] == 'listitem_close'){
                $cnt++;
            }elseif($instructions[$i][0] == 'internallink'){
                $page = cleanID($instructions[$i][1][0]);
                $list[$page] = array(
                                     'parents' => $parents,
                                     'page' => $instructions[$i][1][0],
                                     'title' => $instructions[$i][1][1],
                                     'lvl' => $lvl
                                    );
            }
        }

        return $list;
    }

    /**
     * Create output
     *
     * We handle all modes (except meta) because we pass all output creation back to the parent
     */
    function render($format, &$R, $data) {
        global $INFO;
        global $ID;
        if($format == 'metadata') return false;

        $R->info['cache'] = false; // no cache please

        $parent = (array) $data[$INFO['id']]['parents']; // get the "path" of the page we're on currently
        array_push($parent,$INFO['id']);

        // we need the top ID for the renderer
        $oldid = $ID;
        $ID = $INFO['id'];

        // create a correctly nested list (or so I hope)
        $open = false;
        $lvl  = 1;
        $R->listu_open();
        foreach((array) $data as $pid => $info){
            // only show if we are in the "path"
            if(array_diff($info['parents'],$parent)) continue;

            // skip every non readable page
            if(auth_quickaclcheck(cleanID($info['page'])) < AUTH_READ) continue;

            if($info['lvl'] == $lvl){
                if($open) $R->listitem_close();
                $R->listitem_open($lvl);
                $open = true;
            }elseif($lvl > $info['lvl']){
                for($lvl; $lvl > $info['lvl']; $lvl--){
                  $R->listitem_close();
                  $R->listu_close();
                }
                $R->listitem_close();
                $R->listitem_open($lvl);
            }elseif($lvl < $info['lvl']){
                // more than one run is bad nesting!
                for($lvl; $lvl < $info['lvl']; $lvl++){
                    $R->listu_open();
                    $R->listitem_open($lvl);
                    $open = true;
                }
            }

            $R->listcontent_open();
            $R->internallink($info['page'],$info['title']);
            $R->listcontent_close();
        }
        while($lvl > 0){
            $R->listitem_close();
            $R->listu_close();
            $lvl--;
        }

        $ID = $oldid;

        return true;
    }
}
<?php
namespace NetworkView;

use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin {
    
    public $nodesIds = [];
    public $edgesIds = [];
    public $nodes = [];
    public $edges = [];
    
    public function _init() {
        $app = App::i();
        
        // register translation text domain
        \MapasCulturais\i::load_textdomain( 'networkview', __DIR__ . "/translations" );
        
        $plugin = $this;
        
        $app->hook('ALL(site.network)', function () use($app){
        
            
            $agents = $app->repo('Agent')->findAll();
            $spaces = $app->repo('Space')->findAll();
            
            $nodes = [];
            $edges = [];
            
            foreach ($agents as $agent) {
                $nodes[] = [
                    'id' => 'agent-' . $agent->id,
                    //'label' => $agent->name,
                    'label' => $agent->id,
                    'shape' => 'circle',
                    //'color' => 'blue'
                ];
                
                if (is_object($agent->parent)) {
                    $edges[] = [
                        'from' => 'agent-' . $agent->parent->id,
                        'to' => 'agent-' . $agent->id,
                        //'color' => 'blue'
                    ];
                }
                
            }
            //\dump($spaces); die;
            #foreach ($spaces as $space) {
            #    $nodes[] = [
            #        'id' => 'space-' . $space->id,
            #        //'label' => $agent->name,
            #        'label' => $space->id,
            #        'shape' => 'square',
            #        //'color' => 'blue'
            #    ];
            #    //\dump($space->owner->id); die;
            #    $edges[] = [
            #        'from' => 'agent-' . $space->owner->id,
            #        'to' => 'space-' . $space->id,
            #        //'color' => 'blue'
            #    ];
            #    
            #    #if (is_object($agent->parent)) {
            #    #    $edges[] = [
            #    #        'from' => 'space-' . $space->parent->id,
            #    #        'to' => 'space-' . $space->id,
            #    #        //'color' => 'blue'
            #    #    ];
            #    #}
            #    
            #}
            
            $this->render('search-network', [
                'edges' => $edges,
                'nodes' => $nodes
            ]);
        
        });
        
        
        $app->hook('template(<<agent|space>>.single.tabs):end', function() use($app){
            $this->part('networkview-tab');
        });
        
        $app->hook('template(<<agent>>.single.tabs-content):end', function() use($app, $plugin){

            $center = $this->controller->requestedEntity;
            
            $type = str_replace('MapasCulturais\Entities\\', '', $center->getClassName()); 
            
            $plugin->addNewAgentNode($center);
            
            // agentes que controla
            $controlledAgents = $app->repo('AgentAgentRelation')->findBy(['agent' => $center->id, 'hasControl' => true, 'status' => 1]);
            
            foreach ($controlledAgents as $a) {
                $ag = $a->owner;
                $plugin->addNewAgentNode($ag);

                $plugin->addNewEdge('agent-' . $center->id, 'agent-' . $ag->id, 'controls');
                
                $plugin->exploreChildren($ag, false);

            }
            
            // espacos que controla
            $controlledSpaces = $app->repo('SpaceAgentRelation')->findBy(['agent' => $center->id, 'hasControl' => true, 'status' => 1]);
            
            foreach ($controlledSpaces as $s) {
                $sp = $s->owner;
                
                $plugin->addNewSpaceNode($sp);

                $plugin->addNewEdge('agent-' . $center->id, 'space-' . $sp->id, 'controls');
                
            }
            
            // agentes que me controlam
            $controlledAgents = $app->repo('AgentAgentRelation')->findBy(['owner' => $center->id, 'hasControl' => true, 'status' => 1]);
            
            foreach ($controlledAgents as $a) {
                $ag = $a->agent;
                $plugin->addNewAgentNode($ag);

                $plugin->addNewEdge('agent-' . $ag->id, 'agent-' . $center->id, 'controls');
                
            }
            
            
            
            // filhos e espaços
            $plugin->exploreChildren($center);
            
            // pais
            $parent = $plugin->exploreParents($center);
            
            $this->part('networkview-content', [
                'edges' => $plugin->edges,
                'nodes' => $plugin->nodes
            ]);
            
        });
        
        
    }
    
    
    public function exploreChildren($entity, $exploreControlled = true) {
        $nodes = [];
        $edges = [];
        $app = App::i();    

        $children = $entity->children;
        $spaces = $entity->spaces;
        
        if ($children || $spaces) {
            foreach ($entity->children as $c) {
                
                $this->addNewAgentNode($c);

                $this->addNewEdge('agent-' . $entity->id, 'agent-' . $c->id);
                
                $this->exploreChildren($c);
                
                // spaces
                foreach ($c->spaces as $space) {
                    
                    $this->addNewSpaceNode($space);

                    $this->addNewEdge('agent-' . $c->id, 'space-' . $space->id);
                    
                }
                
                if ($exploreControlled) {
                    
                    // agentes que controla
                    $controlledAgents = $app->repo('AgentAgentRelation')->findBy(['agent' => $c->id, 'hasControl' => true, 'status' => 1]);
                    
                    foreach ($controlledAgents as $a) {
                        $ag = $a->owner;
                        
                        $this->addNewAgentNode($ag);

                        $this->addNewEdge('agent-' . $c->id, 'agent-' . $ag->id, 'controls');
                        
                        $this->exploreChildren($ag, false);
                        
                    }
                    
                    
                    
                    // espacos que controla
                    $controlledSpaces = $app->repo('SpaceAgentRelation')->findBy(['agent' => $c->id, 'hasControl' => true, 'status' => 1]);
                    
                    foreach ($controlledSpaces as $s) {
                        $sp = $s->owner;
                        
                        $this->addNewSpaceNode($sp);

                        $this->addNewEdge('agent-' . $center->id, 'space-' . $sp->id, 'controls');
                
                    }
                    
                }
                
            }
            
            // entity spaces
            foreach ($entity->spaces as $space) {
                
                $this->addNewSpaceNode($space);

                $this->addNewEdge('agent-' . $entity->id, 'space-' . $space->id);
                
            } 
            
            
        } else {
            return false;
        }
        
        return [
            'edges' => $edges,
            'nodes' => $nodes
        ];
        
    }
    
    public function exploreParents($entity) {
        $nodes = [];
        $edges = [];
        
        $type = str_replace('MapasCulturais\Entities\\', '', $entity->getClassName()); 
        if (is_object($entity->parent)) {
            
            $this->addNewAgentNode($entity->parent);

            $this->addNewEdge('agent-' . $entity->parent->id, 'agent-' . $entity->id);
            
            $this->exploreParents($entity->parent);
            
        } else {
            return false;
        }
        
        return [
            'edges' => $edges,
            'nodes' => $nodes
        ];
        
    }
    
    
    
    function addNewAgentNode($agent) {
        
        $id = 'agent-' . $agent->id;
        
        if (in_array($id, $this->nodesIds))
            return;
        
        $this->nodesIds[] = $id;
        
        $this->nodes[] = [
                    'id' => $id,
                    //'label' => $agent->name,
                    'label' => $agent->name,
                    //'shape' => 'circle',
                    //'color' => 'blue'
                ];
        
    }
    
    function addNewSpaceNode($space) {
        
        $id = 'space-' . $space->id;
        
        if (in_array($id, $this->nodesIds))
            return;
        
        $this->nodesIds[] = $id;
        
        $this->nodes[] = [
                    'id' => $id,
                    //'label' => $agent->name,
                    'label' => $space->name,
                    'shape' => 'square',
                    //'color' => 'blue'
                ];
        
    }
    
    function addNewEdge($from, $to, $type = 'default') {
        
        $check = $from . $to;
        
        if (in_array($check, $this->edgesIds))
            return;
        
        $this->edgesIds[] = $check;
        
        $config = [
                'from' => $from,
                'to' => $to,
                'arrows' => 'to'
            ];
        
        if ($type == 'controls') 
            $config['color'] = 'red';
        
        $this->edges[] = $config;
    
    }
    
    public function register() {
        
    }
    
}

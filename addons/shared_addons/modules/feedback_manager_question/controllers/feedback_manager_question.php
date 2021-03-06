<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Feedback_manager_question
 * @Description: This is class Feedback_manager_question
 * @Author: HoangThiTuanDung
 * @Date: 12/25/13
 * @Update: 12/25/13
 */

class Feedback_manager_question extends Public_Controller {

    // Validate
    protected $validation_rules = array(
        'feedback_manager_id'    => array(
            'field'     => 'feedback_manager_id',
            'label'     => 'lang:feedback_manager_question:feedback_manager_id',
            'rules'     => ''
        ),
        'question_id'    => array(
            'field'     => 'question_id',
            'label'     => 'lang:feedback_manager_question:question_id',
            'rules'     => ''
        ),
    );

    /**
    * construct function
    */
    public function __construct(){
        parent::__construct();
        if(!check_user_permission($this->current_user, $this->module, $this->permissions)) redirect();
        $this->template->set_layout('feedback_layout.html');
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        $this->load->driver('Streams');
        $this->load->library(array('keywords/keywords', 'form_validation'));
        $this->stream = $this->streams_m->get_stream('feedback_manager_question', true, 'feedback_manager_questions');
        $this->load->model(array('feedback_manager_question_m', 'feedback_manager/feedback_manager_m', 'question/question_m'));
        $this->lang->load('feedback_manager_question');

        //Get feedback_manager list from cached to bidding select list
        $feedback_manager = array(''  => lang('feedback_manager_question:select_feedback_manager'));
        $feedback_managers = $this->streams->entries->get_entries(array('stream' => 'feedback_manager', 'namespace' => 'feedback_managers'));
        foreach ($feedback_managers['entries'] as $post) {
            $feedback_manager[$post['id']] = $post['title'];
        }
        $this->template->set('feedback_managers', $feedback_manager);

        //Get question list from cached to bidding select list
        $question = array();
        $questions = $this->streams->entries->get_entries(array('stream' => 'question', 'namespace' => 'questions'));
        foreach ($questions['entries'] as $post) {
            $question[$post['id']] = $post['title'];
        }
        $this->template->set('questions', $question);
    }

    /**
     * The index function
     * @Description: This is index function
     * @Parameter:
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    public function index(){
        $where = "";

        // Get the latest feedback manager question posts
        $posts = $this->streams->entries->get_entries(array(
            'stream'		=> 'feedback_manager_question',
            'namespace'		=> 'feedback_manager_questions',
            'limit'         => Settings::get('records_per_page'),
            'where'		    => $where,
            'paginate'		=> 'yes',
            'pag_base'		=> site_url('feedback_manager_question/page'),
            'pag_segment'   => 3
        ));

        // Process posts
        foreach ($posts['entries'] as &$post) {
            $this->_process_post($post);
        }
        $meta = $this->_posts_metadata($posts['entries']);
        $this->input->is_ajax_request() and $this->template->set_layout(false);

        $this->template
            ->title($this->module_details['name'])
            ->set_breadcrumb(lang('feedback_manager_question:feedback_manager_question_title'))
            ->set('breadcrumb_title', $this->module_details['name'])
            ->set_metadata('og:title', $this->module_details['name'], 'og')
            ->set_metadata('og:type', 'feedback_manager_question', 'og')
            ->set_metadata('og:url', current_url(), 'og')
            ->set_metadata('keywords', $meta['keywords'])
            ->append_js('module::feedback_manager_question_form.js')
            ->set_stream($this->stream->stream_slug, $this->stream->stream_namespace)
            ->set('posts', $posts['entries'])
            ->set('pagination', $posts['pagination']);

        $this->input->is_ajax_request()
            ? $this->template->build('tables/posts')
            : $this->template->build('index');
    }

    /**
     * The process function
     * @Description: This is process function
     * @Parameter:
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    public function process(){
        if(!$this->input->is_ajax_request()) redirect('feedback_manager_question');
        if($this->input->post('action') == 'create'){
            $this->create();
        }else if($this->input->post('action') == 'edit'){
            $this->edit();
        }
    }

    /**
     * The delete function
     * @Description: This is delete function
     * @Parameter:
     *      1. $id int The ID of the feedback manager question post to delete
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    public function delete($id = 0) {
        $ids = ($id) ? array($id) : $this->input->post('action_to');
        if (!empty($ids)){
            $post_names = array();
            $deleted_ids = array();
            foreach ($ids as $id){
                if ($post = $this->feedback_manager_question_m->get($id)){
                    if ($this->feedback_manager_question_m->delete($id)){
                        $this->pyrocache->delete('feedback_manager_question_m');
                        $post_names[] = $post->id;
                        $deleted_ids[] = $id;
                    }
                }
            }
            Events::trigger('feedback_manager_question_deleted', $deleted_ids);
        }
        $message = array();
        if (!empty($post_names)){
            if (count($post_names) == 1) {
                $message['status']  = 'success';
                $message['message']  = str_replace("%s", $post_names[0], lang('feedback_manager_question:delete_success'));
            } else {
                $message['status']  = 'success';
                $message['message']  = str_replace("%s", implode('", "', $post_names), lang('feedback_manager_question:mass_delete_success'));
            }
        } else {
            $message['status']  = 'warning';
            $message['message']  = lang('feedback_manager_question:delete_error');
        }
        echo json_encode($message);
    }

    /**
     * The action function
     * @Description: This is action function
     * @Parameter:
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    public function action()
    {
        switch ($this->input->post('btnAction'))
        {
            case 'delete':
                $this->delete();
                break;
            default:
                echo '';
                break;
        }
    }

    /**
     * The get_feedback_manager_question_by_id function
     * @Parameter: $id
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    public function get_feedback_manager_question_by_id($id){
        if(!$this->input->is_ajax_request()) redirect('feedback_manager_question');
        if($id != null && $id != ""){
            $item = $this->feedback_manager_question_m->get($id);
            echo json_encode($item);
        }else{
            echo "";
        }
    }

    /**
     * The create function
     * @Description: This is create function
     * @Parameter:
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    private function create(){
        $message = array();
        $stream = $this->streams->streams->get_stream('feedback_manager_question', 'feedback_manager_questions');
        // Get the validation for our custom blog fields.
        $feedback_manager_question_validation = $this->streams->streams->validation_array($stream->stream_slug, $stream->stream_namespace, 'new');
        $rules = array_merge($this->validation_rules, $feedback_manager_question_validation);
        $this->form_validation->set_rules($rules);

        if ($this->form_validation->run()){
            $extra = array(
                'feedback_manager_id'   => $this->input->post('feedback_manager_id'),
                'created'		        => date('Y-m-d H:i:s', now()),
                'created_by'            => $this->current_user->id
            );

             $data = $this->input->post('myselect');

             foreach ($data as $item) {
                $extra['question_id'] = $item;

                if ($id = $this->streams->entries->insert_entry($_POST, 'feedback_manager_question', 'feedback_manager_questions', array('created'), $extra)) {
                    $this->pyrocache->delete_all('feedback_manager_question_m');
                    $message['status']  = 'success';
                    $message['message']  = "Success";
                    Events::trigger('feedback_manager_question_created', $id);
                } else {
                    $message['status']  = 'error';
                    $message['message']  = lang('feedback_manager_question:post_add_error');
                }
            }
        } else {
            $message['status']  = 'error';
            $message['message']  = lang('feedback_manager_question:validate_error');
        }
        echo json_encode($message);
    }

    /**
     * The edit function
     * @Description: This is edit function
     * @Parameter:
     *      1. $id int The ID of the feedback manager question post to edit
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    private function edit(){
        $id = $this->input->post('row_edit_id');
        $post = $this->feedback_manager_question_m->get($id);
        $message = array();

        $stream = $this->streams->streams->get_stream('feedback_manager_question', 'feedback_manager_questions');
        $feedback_manager_question_validation = $this->streams->streams->validation_array($stream->stream_slug, $stream->stream_namespace, 'new');
        $rules = array_merge($this->validation_rules, $feedback_manager_question_validation);
        $this->form_validation->set_rules($rules);

        if ($this->form_validation->run()){
            $author_id = empty($post->created_by) ? $this->current_user->id : $post->created_by;
            $extra = array(
                'feedback_manager_id'   => $this->input->post('feedback_manager_id'),
                'updated'		    => date('Y-m-d H:i:s', now()),
                'created_by'        => $author_id
            );

            $data = $this->input->post('myselect');

                
            if ($post = $this->feedback_manager_question_m->get_by(array('id'=>$id))){
                if($test = $this->feedback_manager_question_m->get_many_by(array('feedback_manager_id'=>$post->feedback_manager_id)))
                {
                    foreach ($test as $t) 
                    {
                        $this->feedback_manager_question_m->delete($t->id);
                        $this->pyrocache->delete('feedback_manager_question_m');
                    }
                }
            }
            foreach ($data as $item) {
                $extra['question_id'] = $item;

                if ($id = $this->streams->entries->insert_entry($_POST, 'feedback_manager_question', 'feedback_manager_questions', array('updated'), $extra)) {
                    $this->pyrocache->delete_all('feedback_manager_question_m');
                    $message['status']  = 'success';
                    $message['message']  = "Success";
                    Events::trigger('feedback_manager_question_updated', $id);
                } else {
                    $message['status']  = 'error';
                    $message['message']  = lang('feedback_manager_question:edit_error');
                }
            }
        } else {
            $message['status']  = 'error';
            $message['message']  = lang('feedback_manager_question:validate_error');
        }
        echo json_encode($message);
    }

    /**
     * The process_post function
     * @Description: This is process_post function
     * @Parameter:
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    private function _process_post(&$post) {
        $post['feedback_manager'] = $this->feedback_manager_m->get($post['feedback_manager_id'])->title;
        $post['question'] = $this->question_m->get($post['question_id'])->title;
        $post['url_edit'] = site_url('feedback_manager_question/edit/'.$post['id']);
        $post['url_delete'] = site_url('feedback_manager_question/delete/'.$post['id']);
    }

    /**
     * The posts_metadata function
     * @Description: This is posts_metadata function
     * @Parameter:
     * @Return: null
     * @Date: 11/21/13
     * @Update: 11/21/13
     */
    private function _posts_metadata(&$posts = array()) {
        $keywords = array();
        $description = array();

        if (!empty($posts)) {
            foreach ($posts as &$post){
                $keywords[] = $post['feedback_manager'];
            }
        }

        return array(
            'keywords' => implode(', ', $keywords),
        );
    }
} 

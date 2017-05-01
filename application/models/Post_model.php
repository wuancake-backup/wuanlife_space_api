<?php



class Post_model extends CI_Model
{
    public function __construct()
    {
        $this->load->database();
    }
    /**
     * @param $text
     * @param $pnum
     * @param $pn
     * @return mixed
     * 搜索帖子
     */
    public function search_posts($text,$pnum,$pn){
        $text = strtolower($text);
        $num=($pn-1)*$pnum;
        $sql = 'SELECT pb.id AS post_id,pb.title AS p_title,pd.text AS p_text,pd.create_time,ub.nickname AS user_name,ub.id AS user_id,gb.id AS group_id,gb.name AS g_name '
            . 'FROM post_detail pd,post_base pb ,group_base gb,user_base ub '
            . "WHERE pb.id=pd.post_base_id AND pb.user_base_id=ub.id AND pb.group_base_id=gb.id AND pb.delete='0' AND gb.delete='0' AND gb.private='0' "
            . "AND lower(pb.title) LIKE '%$text%' "
            . 'GROUP BY pb.id '
            . 'ORDER BY COUNT(pd.post_base_id) DESC '
            . "LIMIT $num,$pnum";
        $query = $this->db->query($sql)->result_array();
        return $query;
    }
    /**
     * @param $text
     * @return mixed
     * 搜索帖子数量
     */
    public function search_posts_num($text){
        $text = strtolower($text);
        $sql = 'SELECT count(*) AS num '
            . "FROM post_base pb,group_base gb WHERE pb.delete=0 AND pb.group_base_id=gb.id AND gb.private='0' AND gb.delete='0'"
            . "AND lower(pb.title) LIKE '%$text%'";
        $query = $this->db->query($sql);
        return $query->row_array()['num'];
    }
    /**
     * @param $post_id
     * @return mixed
     * 通过帖子id查找所属星球id
     */
    public function get_group_id($post_id){
        return $this->get_post_information($post_id)['group_base_id'];
    }
    /**
     * @param $post_id
     * @param $user_id
     * @return mixed
     * 单个帖子的内容详情，不包括回复列表
     */
    public function get_post_base($post_id,$user_id){
        if(empty($user_id)){
            $user_id = 0;
        }
        $sql = 'SELECT gb.id AS group_id,gb.name AS g_name,gb.g_image,gb.g_introduction,pb.id AS post_id,pb.title AS p_title,pd.text AS p_text,'
            .'ub.id AS user_id,ub.nickname AS user_name,ud.profile_picture,'
            .'pd.create_time,pb.sticky,pb.`lock`,'
            ."(SELECT count(approved) FROM post_approved WHERE user_base_id=$user_id AND post_base_id=$post_id AND floor=1) AS approved,"
            ."(SELECT count(approved) FROM post_approved WHERE floor=1 AND post_base_id=$post_id AND approved=1) AS approved_num,"
            ."(SELECT count(user_base_id) FROM user_collection WHERE user_base_id=$user_id AND post_base_id=$post_id AND `delete`=0) AS collected,"
            ."(SELECT count(user_base_id) FROM user_collection WHERE post_base_id=$post_id AND `delete`=0) AS collected_num "
            . 'FROM post_detail pd,post_base pb ,group_base gb,user_base ub,user_detail ud '
            . 'WHERE pb.id=pd.post_base_id AND pb.`delete`=0 AND pb.user_base_id=ub.id AND pb.group_base_id=gb.id '
            ."AND pb.id=$post_id AND pd.floor=1 AND ub.id=ud.user_base_id" ;
        $rs = $this->db->query($sql)->row_array();
        if (!empty($rs)){
            $rs['sticky']=(int)$rs['sticky'];
            $rs['lock']=(int)$rs['lock'];
            preg_match_all("(http://[-a-zA-Z0-9@:%_\+.~#?&//=]+[.jpg.gif.png])",$rs['p_text'],$rs['p_image']);
        }
        if(empty($rs['profile_picture'])){
            $rs['profile_picture'] = 'http://7xlx4u.com1.z0.glb.clouddn.com/o_1aqt96pink2kvkhj13111r15tr7.jpg?imageView2/1/w/100/h/100';
        }
        if(empty($rs['g_image'])){
            $rs['g_image'] = 'http://7xlx4u.com1.z0.glb.clouddn.com/o_1aqt96pink2kvkhj13111r15tr7.jpg?imageView2/1/w/100/h/100';
        }
        return $rs;
    }
    /**
     * @param $post_id
     * @param $pn
     * @param $user_id
     * @return array
     * 单个帖子的回复详情，不包括帖子内容
     */
    public function get_post_reply($post_id,$pn,$user_id){
        $num=30;                    //每页显示数量
        $rs   = array();
        if(empty($user_id)){
            $user_id = 0;
        }
        $rs['post_id']=$post_id;
        $sql = "SELECT ceil(count(pd.post_base_id)/$num) AS page_count,count(*) AS reply_count "
            . 'FROM post_detail as pd '
            . "WHERE pd.post_base_id=$post_id AND pd.floor>1 AND pd.delete=0 ";
        $count = $this->db->query($sql)->result_array();
        $rs['reply_count'] = (int)$count[0]['reply_count'];
        $rs['page_count'] = (int)$count[0]['page_count'];
        if ($rs['page_count'] == 0 ){
            $rs['page_count']=1;
        }
        if($pn > $rs['page_count']){
            $pn = $rs['page_count'];
        }
        $limsit_st = ($pn-1)*$num;
        $rs['current_page'] = $pn;
        $sql = 'SELECT pd.reply_floor,pd.text AS p_text,ub.id AS user_id,ub.nickname AS user_name,pd.reply_id,'
            .'(SELECT nickname FROM user_base WHERE user_base.id = pd.reply_id) AS reply_nick_name,'
            .'pd.create_time,pd.floor AS p_floor,'
            ."(SELECT approved FROM post_approved WHERE user_base_id=$user_id AND post_base_id=$post_id AND floor=pd.floor) AS approved,"
            ."(SELECT count(approved) FROM post_approved WHERE floor=pd.floor AND post_base_id=$post_id AND approved=1) AS approvednum "
            . 'FROM user_base ub,post_detail pd '
            . "WHERE pd.post_base_id = $post_id AND pd.`delete` = 0 AND pd.floor > 1 AND ub.id=pd.user_base_id "
            . 'ORDER BY pd.floor ASC '
            . "LIMIT $limsit_st,$num ";
        $rs['reply'] = $count = $this->db->query($sql)->result_array();
        foreach ($rs['reply'] as $key => $value) {
            if(empty($rs['reply']["$key"]['approved'])){
                $rs['reply']["$key"]['approved'] = '0';
            }
        }
        return $rs;
    }
    /**
     * @param $post_id
     * @return mixed
     * 通过帖子ud获取帖子详情
     */
    public function get_post_information($post_id){
        $this->db->select('*');
        $this->db->from('post_base');
        $this->db->where('id',$post_id);
        $this->db->join('post_detail', 'post_detail.post_base_id = post_base.id');
        $this->db->where('floor',1);
        $query = $this->db->get();
        return $query->row_array();
    }

    /**
     * @param $p_id
     * @param $floor
     * @return bool|int
     * 查询帖子回复所在的页数
     */
    public function get_post_reply_page($p_id,$floor){
        $num=30;
        $sql = "SELECT ceil(count(pd.post_base_id)/$num) AS page_count,count(*) AS reply_count "
            . 'FROM post_detail as pd '
            . "WHERE pd.post_base_id=$p_id AND pd.floor>1 AND pd.delete=0 ";
        $count = $this->db->query($sql)->result_array();
        for($i=1;$i<=$count[0]['page_count'];$i++){
            $floors = $this->db->from('post_detail')
                ->SELECT('floor')
                ->WHERE('post_base_id',$p_id)
                ->WHERE('floor >',1)
                ->WHERE('delete',0)
                ->limit(($i-1)*$num,$num)
                ->get()
                ->result_array();
            foreach($floors as $key =>$value){
                if($value['floor'] == $floor){
                    return $i;
                }
            }
        }
        if($num>$floor){
            return 1;
        }
        return false;
    }
    public function get_index_post($data){
        $num=10;
        if($data['user_id']==null){
            $data['user_id']=0;
        }
        $user_id=$data['user_id'];
        $rs=array();
        $sql = "SELECT ceil(count(*)/$num) AS page_count "
            . "FROM post_base pb,group_base gb WHERE pb.delete=0 AND pb.group_base_id=gb.id AND gb.private='0' AND gb.delete='0'";
        $page_count=$this->db->query($sql)->result_array()[0];
        $rs['page_count'] = (int)$page_count['page_count'];
        if ($rs['page_count'] == 0 ){
            $rs['page_count']=1;
        }
        if($data['page'] > $rs['page_count']){
            $data['page'] = $rs['page_count'];
        }elseif ($data['page']==null){
            $data['page']=1;
        }
        $start=($data['page']-1)*$num;
        $rs['currentPage'] = (int)$data['page'];
        $sql = 'SELECT pb.id AS post_id,pb.title as p_title,pd.text as p_text,pb.`lock`,pd.create_time,'
            .'ud.profile_picture,ub.id AS user_id,'
            .'ub.nickname as user_name,gb.id AS group_id,gb.`name` AS g_name,'
            ."(SELECT count(approved) FROM post_approved WHERE user_base_id=$user_id AND post_base_id=pb.id AND floor=1) AS approved,"
            .'(SELECT count(approved) FROM post_approved WHERE floor=1 AND post_base_id=pb.id AND approved=1) AS approved_num,'
            ."(SELECT count(user_base_id) FROM user_collection WHERE user_base_id=$user_id AND post_base_id=pb.id AND `delete`=0) AS collected,"
            ."(SELECT count(user_base_id) FROM user_collection WHERE post_base_id=pb.id AND `delete`=0) AS collected_num,"
            ."(SELECT count(user_base_id) FROM post_detail WHERE user_base_id=$user_id AND post_base_id=pb.id AND floor>1 AND `delete`=0) AS replied,"
            .'(SELECT count(user_base_id) FROM post_detail WHERE post_base_id=pb.id AND floor>1 AND `delete`=0) AS replied_num '
            .'FROM post_detail pd,post_base pb ,group_base gb,user_base ub,user_detail ud '
             .'WHERE pb.id=pd.post_base_id AND pb.user_base_id=ub.id '
             .'AND ub.id=ud.user_base_id '
             .'AND pb.group_base_id=gb.id AND pb.delete=0 '
             .'AND gb.delete=0 AND gb.private=0 '
             . 'GROUP BY pb.id '
             . 'ORDER BY MIN(pd.create_time) DESC '
             . "LIMIT $start,$num ";
        $this->db->flush_cache();
        $rs['posts']=$this->db->query($sql)->result_array();
        foreach ($rs['posts'] as $key => $value) {
            if($value['replied']>0){
                $rs['posts']["$key"]['replied'] = '1';
            }
            if(empty($value['profile_picture'])){
                $rs['posts']["$key"]['profile_picture'] = 'http://7xlx4u.com1.z0.glb.clouddn.com/o_1aqt96pink2kvkhj13111r15tr7.jpg?imageView2/1/w/100/h/100';
            }
        }
        return $rs;
    }

    public function get_image_url($data){
        $rs = $data;
        for ($i=0; $i<count($rs['posts']); $i++) {
            $rs['posts'][$i]['p_text'] = str_replace('\"', '', $rs['posts'][$i]['p_text']);
            preg_match_all('/<img[^>]*src\s?=\s?[\'|"]([^\'|"]*)[\'|"]/is', $rs['posts'][$i]['p_text'], $picarr);
            $rs['posts'][$i]['image']=$picarr['1'];
        }
        return $rs;
    }

    /*
    * 过滤帖子列表image中gif格式的url
     */
    public function delete_image_gif($data){
        $rs = $data;
        $datab = "/([http|https]):\/\/.*?\.gif/";
        foreach ($rs['posts'] as $key1 => $value) {
            if(!empty($value['image'])){
                foreach ($value['image'] as $key2 => $image) {
                    if(preg_match($datab, $image)){
                        unset($rs['posts'][$key1]['image'][$key2]);
                    }
                }
            }
        }
        return $rs;

    }

    /**
     * @param $data
     * @return array
     * 帖子回复
     */
    public function post_reply($data) {
        $time = date('Y-m-d H:i:s',time());
        //查询最大楼层
        $sql=$this->db->from('post_detail')
            ->select('post_base_id,user_base_id,max(floor)')
            ->where('post_base_id',$data['post_base_id'])
            ->get()
            ->row_array();
        $data['create_time'] = $time;
        $data['floor'] = ($sql['max(floor)'])+1;
        $reply_id=$this->db->from('post_detail')
            ->select('user_base_id')
            ->where('post_base_id',$data['post_base_id'])
            ->where('floor',$data['reply_floor'])
            ->get()
            ->row_array();
        $data['reply_id']=$reply_id['user_base_id'];
        $rs = $this->db->insert('post_detail',$data);
        if($rs){
            return $data;
        }else{
            return false;
        }
    }
    /**
     * @param $data
     * 编辑帖子
     */
    public function edit_post($data){
        $b_data = array(
            'title' => $data['title'],
        );
        $d_data = array(
            'text' => $data['text'],
            'create_time' => time(),
        );
        $this->db->where('id', $data['post_base_id'])
            ->update('post_base',$b_data);
        $this->db->where('post_base_id', $data['post_base_id'])
            ->where('floor',1)
            ->update('post_detail',$d_data);
    }
    /*
     * 设置帖子列表image图片url上限
     */
    public function post_image_limit($data){
        $rs=$data;
        foreach ($rs['posts'] as $key => $value) {
            if(count($value['image'])>3){
                $rs['posts'][$key]['image'] = array_slice($value['image'],0,3);
            }
        }
        return $rs;
    }
    /*
     * 删除帖子列表html
     */
    public function delete_html_posts($data){
        $rs = $data;
        for ($i=0; $i<count($rs['posts']); $i++) {
            $rs['posts'][$i]['p_text'] = strip_tags($rs['posts'][$i]['p_text']);

        }
        return $rs;
    }

    /*
    帖子预览文本限制100
     */
    public function post_text_limit($data){
        $rs=$data;
        for ($i=0; $i<count($rs['posts']); $i++) {
            $rs['posts'][$i]['p_text'] =mb_convert_encoding(substr($rs['posts'][$i]['p_text'],0,299), 'UTF-8','GB2312,UTF-8');
        }
        return $rs;
    }


    /*
     * 我的星球帖子展示
     */
    public function get_mygroup_post($user_id,$page=null) {
        $num=10;
        $rs   = array();

        $sql = "SELECT ceil(count(*)/$num) AS page_count "
            . 'FROM post_base pb,group_base gb,group_detail gd '
            . "WHERE pb.group_base_id=gb.id AND gb.id=gd.group_base_id AND gd.user_base_id=$user_id AND pb.delete=0 AND gb.delete=0 ";
        $page_count=$this->db->query($sql)->row_array();

        $rs['page_count'] = (int)$page_count['page_count'];
        if ($rs['page_count'] == 0 ){
            $rs['page_count']=1;
        }
        if($page > $rs['page_count']){
            $page = $rs['page_count'];
        }elseif ($page==null){
            $page=1;
        }
        $start=($page-1)*$num;
        $rs['current_page'] = (int)$page;
        $sql = 'SELECT pb.id AS post_id,pb.title as p_title,pd.text as p_text,pb.lock,pd.create_time,'
            . 'ub.nickname as user_name,ub.id AS user_id,gb.id AS group_id,gb.name AS g_name,ud.profile_picture,'
            ."(SELECT count(approved) FROM post_approved WHERE user_base_id=$user_id AND post_base_id=pb.id AND floor=1) AS approved,"
            .'(SELECT count(approved) FROM post_approved WHERE floor=1 AND post_base_id=pb.id AND approved=1) AS approved_num,'
            ."(SELECT count(user_base_id) FROM user_collection WHERE user_base_id=$user_id AND post_base_id=pb.id AND `delete`=0) AS collected,"
            ."(SELECT count(user_base_id) FROM user_collection WHERE post_base_id=pb.id AND `delete`=0) AS collected_num,"
            ."(SELECT count(user_base_id) FROM post_detail WHERE user_base_id=$user_id AND post_base_id=pb.id AND floor>1 AND `delete`=0) AS replied,"
            .'(SELECT count(user_base_id) FROM post_detail WHERE post_base_id=pb.id AND floor>1 AND `delete`=0) AS replied_num '
            . 'FROM post_detail pd,post_base pb ,group_base gb,user_base ub,user_detail ud '
            . 'WHERE pb.id=pd.post_base_id AND pb.user_base_id=ub.id AND pb.group_base_id=gb.id AND pb.delete=0 AND gb.delete=0 '
            . "AND gb.id in (SELECT group_base_id FROM group_detail gd WHERE gd.user_base_id =$user_id ) AND ub.id=ud.user_base_id "
            . 'GROUP BY pb.id '
            . 'ORDER BY MIN(pd.create_time) DESC '
            . "LIMIT $start,$num ";
        $this->db->flush_cache();
        $rs['posts']=$this->db->query($sql)->result_array();
        foreach ($rs['posts'] as $key => $value) {
            if($value['replied']>0){
                $rs['posts']["$key"]['replied'] = '1';
            }
            if(empty($value['profile_picture'])){
                $rs['posts']["$key"]['profile_picture'] = 'http://7xlx4u.com1.z0.glb.clouddn.com/o_1aqt96pink2kvkhj13111r15tr7.jpg?imageView2/1/w/100/h/100';
            }
        }
        return $rs;
    }


    /**
     * 通过用户id获得用户昵称
     * User_model已存在相同方法get_user_information
     */
    public function get_user($user_id){
        $re=$this->db->select('nickname')
            ->where('id',$user_id)
            ->get('user_base')
            ->row_array();
        return $re['nickname'];
    }



    /**
     * 通过星球id返回星球创建者昵称
     * Group_model已存在相同方法get_group_information
     */
    public function get_creator($group_id){
        $re=$this->db->select('user_base_id')
            ->where('group_base_id',$group_id)
            ->where('authorization','01')
            ->get('group_detail')
            ->row_array();
        $user_base_id=$re['user_base_id'];
        $this->db->flush_cache();
        $re=$this->db->select('nickname')
            ->where('id',$user_base_id)
            ->get('user_base')
            ->row_array();
        return $re['nickname'];
    }


    public function get_group_post($group_id,$page=null,$user_id){
        if(empty($user_id)){
            $user_id = 0;
        }
        $num=9;
        $rs   = array();
        $sql = "SELECT ceil(count(*)/$num) AS page_count "
            . 'FROM post_base pb,group_base gb '
            . "WHERE pb.group_base_id=gb.id AND gb.id=$group_id AND pb.delete=0 ";
        $page_count = $this->db->query($sql)->row_array();
        $rs['page_count'] = (int)$page_count['page_count'];
        if ($rs['page_count'] == 0 ){
            $rs['page_count']=1;
        }
        if($page > $rs['page_count']){
            $page = $rs['page_count'];
        }elseif ($page==null){
            $page=1;
        }
        $rs['current_page'] = $page;
        $start=($page-1)*$num;
        $sql = 'SELECT  pb.id AS post_id,pb.title AS p_title,pd.text as p_text,pd.create_time,ub.id AS user_id,ub.nickname as user_name,pb.sticky,pb.lock,pb.digest,ud.profile_picture,'
            ."(SELECT count(approved) FROM post_approved WHERE user_base_id=$user_id AND post_base_id=pb.id AND floor=1) AS approved,"
            .'(SELECT count(approved) FROM post_approved WHERE floor=1 AND post_base_id=pb.id AND approved=1) AS approved_num,'
            ."(SELECT count(user_base_id) FROM user_collection WHERE user_base_id=$user_id AND post_base_id=pb.id AND `delete`=0) AS collected,"
            ."(SELECT count(user_base_id) FROM user_collection WHERE post_base_id=pb.id AND `delete`=0) AS collected_num,"
            ."(SELECT count(user_base_id) FROM post_detail WHERE user_base_id=$user_id AND post_base_id=pb.id AND floor>1 AND `delete`=0) AS replied,"
            .'(SELECT count(user_base_id) FROM post_detail WHERE post_base_id=pb.id AND floor>1 AND `delete`=0) AS replied_num '
            . 'FROM post_detail pd,post_base pb ,group_base gb,user_base ub,user_detail ud '
            . "WHERE pb.id=pd.post_base_id AND pb.user_base_id=ub.id AND pb.group_base_id=gb.id AND pb.group_base_id=$group_id AND pb.delete=0 AND ub.id = ud.user_base_id "
            . 'GROUP BY pb.id '
            . 'ORDER BY pb.sticky DESC, '
            . 'MAX(pd.create_time) DESC '
            . "LIMIT $start,$num ";
        $this->db->flush_cache();

        $rs['posts'] = $this->db->query($sql)->result_array();
        foreach ($rs['posts'] as $key => $value) {
            if($value['replied']>0){
                $rs['posts']["$key"]['replied'] = '1';
            }
            if(empty($value['profile_picture'])){
                $rs['posts']["$key"]['profile_picture'] = 'http://7xlx4u.com1.z0.glb.clouddn.com/o_1aqt96pink2kvkhj13111r15tr7.jpg?imageView2/1/w/100/h/100';
            }
        }
        return $rs;
}
    public function post_reply_message($rs){
        $create_id = $this->get_post_information($rs['post_base_id'])['user_base_id'];
        if(empty($rs['reply_id'])){
            $rs['reply_id'] = $create_id;
        }
        if($rs['user_base_id']==$create_id){
            return false;
        }
        $data = array(
            'user_base_id' =>$rs['reply_id'],
            'user_reply_id'=>$rs['user_base_id'],
            'post_base_id'=>$rs['post_base_id'],
            'reply_floor'=>$rs['floor'],
            'create_time'=>time(),
            'status'=>0,
        );
        $this->db->insert('message_reply',$data);
    }

    public function post_sticky(){
        $data = array(
                'post_id' => $this->input->get('post_id'),
        );
        $param = array(
                'sticky' => 1,
        );
        if(!empty($data['post_id']))
        {
            $this->db->where('id',$data['post_id']);
            $re=$this->db->update('post_base', $param);
            return $re;
        }
        else
        {
            return FALSE;
        }
    }

    public function post_unsticky(){
        $data = array(
                'post_id' => $this->input->get('post_id'),
        );
        $param = array(
                'sticky' => 0,
        );
        
        if(!empty($data['post_id']))
        {
            $this->db->where('id',$data['post_id']);
            $re=$this->db->update('post_base', $param);
            return $re;
        }
        else
        {
            return FALSE;
        }
    }

    public function judge_poster($user_id,$post_id){
        $sql=$this->db->select('floor')
            ->from('post_detail')
            ->where('user_base_id',$user_id)
            ->where('post_base_id',$post_id)
            ->get()
            ->row_array();
        if($sql['floor']==1){
            $rs=1;
        }else{
            $rs=0;
        }
        return $rs;
    }

    public function delete_post($data){
        $d_data = array(
            '`delete`' => '1',
        );
        $this->db->where('id', $data['post_id'])
            ->update('post_base',$d_data);
        $rs['code']=1;
        return $rs;
    }

    public function lock_post(){
        $data = array(
                'post_id' => $this->input->get('post_id'),
        );
        $param = array(
                'lock' => 1,
        );
        if(!empty($data['post_id']))
        {
            $this->db->where('id',$data['post_id']);
            $re=$this->db->update('post_base', $param);
            return $re;
        }
        else
        {
            return FALSE;
        }
    }

        public function unlock_post(){
        $data = array(
                'post_id' => $this->input->get('post_id'),
        );
        $param = array(
                'lock' => 0,
        );
        if(!empty($data['post_id']))
        {
            $this->db->where('id',$data['post_id']);
            $re=$this->db->update('post_base', $param);
            return $re;
        }
        else
        {
            return FALSE;
        }
    }

    public function exist_collect_post($data){
        $time=time();
        $d_data=array('delete' => '0',
            'create_time'=>$time);
        $re=$this->db->where('post_base_id',$data['post_id'])
            ->where('user_base_id',$data['user_id'])
            ->update('user_collection',$d_data);
        return $re;
    }

    public function collect_post($data){
        $i_data=array(
            'post_base_id'=>$data['post_id'],
            'user_base_id'=>$data['user_id'],
            'create_time'=>time(),
        );
        $re=$this->db->insert('user_collection',$i_data);
        return $re;
    }

    public function get_collect_post($data){
        $num=20;
        if($data['user_id']==null){
            $data['user_id']=0;
        }
        $user_id=$data['user_id'];
        $rs=array();
        $sql = "SELECT ceil(count(*)/$num) AS page_count "
             . 'FROM user_collection '
             . "WHERE user_collection.user_base_id='$user_id' AND user_collection.delete=0 ";
        $page_count=$this->db->query($sql)->result_array()[0];

        $rs['page_count'] = (int)$page_count['page_count'];
        if ($rs['page_count'] == 0 ){
            $rs['page_count']=1;
        }
        if($data['page'] > $rs['page_count']){
            $data['page'] = $rs['page_count'];
        }elseif ($data['page']==null){
            $data['page']=1;
        }
        $start=($data['page']-1)*$num;
        $rs['current_page'] = (int)$data['page'];
        $sql = 'SELECT pb.id AS post_id,uc.create_time,pb.title AS p_title,gb.id AS group_id,gb.name AS g_name,ub.nickname AS user_name,pb.delete,pd.text AS p_text '
             . 'FROM user_collection uc,post_base pb,group_base gb,user_base AS ub,post_detail pd '
             . "WHERE pb.id=uc.post_base_id AND pb.group_base_id=gb.id AND uc.delete=0 AND uc.user_base_id=$user_id AND uc.delete=0 AND pb.user_base_id=ub.id AND pd.post_base_id = uc.post_base_id AND pd.floor=1 "
              . "LIMIT $start,$num ";
        $this->db->flush_cache();
        $rs['posts']=$this->db->query($sql)->result_array();
        foreach ($rs['posts'] as $key => $value) {
            $rs['posts']["$key"]['create_time']=date('Y-m-d H:i:s',$rs['posts']["$key"]['create_time']);
        }
        return $rs;
    }

    public function delete_collect_post($data){
        $param = array(
                'delete' => 1,
        );
        if(!(empty($data['post_id'])||empty($data['user_id'])))
        {
            $this->db->where('post_base_id',$data['post_id'])
                ->where('user_base_id',$data['user_id']);
            $re=$this->db->update('user_collection', $param);
            return $re;
        }
        else
        {
            return FALSE;
        }
    }

    public function get_approve_post($data){
      $sql=$this->db->select('*')
          ->from('post_approved')
          ->where('post_base_id',$data['post_id'])
          ->where('user_base_id',$data['user_id'])
          ->where('floor',$data['floor'])
          ->get()
          ->row_array();
      return $sql;
    }

    public function update_approve_post($data){
        $approved = $this->get_approve_post($data);
        if($approved['approved']){
            $field = array('approved'=>0);
            $rs['code'] = 2;
            $rs['msg'] = '取消点赞成功';
        }else{
            $field = array('approved'=>1);
            $rs['code'] = 1;
            $rs['msg'] = '点赞成功';
        }
        $sql=$this->db->where('post_base_id',$data['post_id'])
            ->where('user_base_id',$data['user_id'])
            ->where('floor',$data['floor'])
            ->update('post_approved',$field);
        if($sql){
            return $rs;
        }else{
            $rs['code'] = 0;
            $rs['msg'] = '操作失败';
            return $rs;
        }
    }
    public function add_approve_post($data){
        $field = array(
                    'user_base_id' => $data['user_id'],
                    'post_base_id' => $data['post_id'],
                    'floor'   => $data['floor'],
                    'approved' => 1,
        );
        $sql=$this->db->insert('post_approved',$field);
        if($sql){
            $rs['code'] = 1;
            $rs['msg']  = '点赞成功';
        }else{
            $rs['code'] = 0;
            $rs['msg']  = '点赞失败';
        }
        return $rs;
    }

    public function judge_post_reply_user($user_id,$post_id,$floor){
        $sql=$this->db->select('post_base_id')
            ->from('post_detail')
            ->where('post_base_id',$post_id)
            ->where('user_base_id',$user_id)
            ->where('floor',$floor)
            ->get()
            ->row_array();
        return $sql;
    }


    public function delete_post_reply($data){
        $d_data=array(
            'delete'=>1,
        );
        $this->db->where('post_base_id',$data['post_id'])
            ->where('floor',$data['floor'])
            ->update('post_detail',$d_data);
        $rs['code']=1;
        return $rs;
    }


}
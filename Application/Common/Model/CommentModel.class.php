<?php
namespace Common\Model;
use Think\Model;
/**
* 评论model
*/
class CommentModel extends Model{

	// 添加数据
	public function addData($type){
		$data=I('post.');
		$ouid=$_SESSION['user']['id'];
		// 删除开头或者结尾的换行
		$comment_content=trim($data['content'],'&lt;br&gt;');
		$comment=array(
			'ouid'=>$ouid,
			'type'=>$type,
			'aid'=>$data['aid'],
			'pid'=>$data['pid'],
			'content'=>$comment_content,
			'date'=>time(),
			'status'=>1
			);
		$cmtid=$this->add($comment);
		// 发送通知邮件
		if(C('COMMENT_SEND_EMAIL')){
			$address=C('EMAIL_RECEIVE');
			if(!empty($address)){
				$nickname=M('Oauth_user')->getFieldById($ouid,'nickname');
				$title=M('Article')->getFieldByAid($data['aid'],'title');
				$url=U('Home/Index/article',array('aid'=>$data['aid']),'',true);
				$date=date('Y-m-d H:i:s');
				$content=<<<html
站长你好：<br>
&emsp; $nickname $date 评论了您的文章 <a href="$url">$title</a> 内容如下:<br>
$comment_content

html;
				send_email($address,$nickname.'评论了 '.$title,$content);
			}
		}
		return $cmtid;
	}

	/**
	 * 获取分页数据供后台使用
	 * @param  int   是否删除
	 * @return array 评论数据
	 */
	public function getDataByState($is_delete){
		$count=$this
			->alias('c')
			->join('__ARTICLE__ a ON a.aid=c.aid')
			->join('__OAUTH_USER__ ou ON ou.id=c.ouid')
			->where(array('c.is_delete'=>$is_delete))
			->count();
		$page=new \Org\Bjy\Page($count,10);
		$list=$this
			->field('c.*,a.title,ou.nickname')
			->alias('c')
			->join('__ARTICLE__ a ON a.aid=c.aid')
			->join('__OAUTH_USER__ ou ON ou.id=c.ouid')
			->where(array('c.is_delete'=>$is_delete))
			->limit($page->firstRow.','.$page->listRows)
			->order('date desc')
			->select();
		$data=array(
			'data'=>$list,
			'page'=>$page->show()
			);

		return $data;
	}

	/**
	 * 传递文章id获取树状结构的评论
	 * @param  int $aid 文章id
	 * @return array    树状结构数组
	 */
	public function getChildData($aid){
		// 组合where条件
		$status=C('COMMENT_REVIEW') ? array('c.status'=>1) : array();
		$other=array(
			'c.aid'=>$aid,
			'c.pid'=>0,
			'c.is_delete'=>0
			);
		$where=array_merge($status,$other);
		// 关联第三方用户表获取一级评论
		$data=$this
			->field('c.*,ou.nickname,ou.head_img')
			->alias('c')
			->join('__OAUTH_USER__ ou ON c.ouid=ou.id')
			->where($where)
			->order('date desc')
			->select();
		foreach ($data as $k => $v) {
			$data[$k]['content']=htmlspecialchars_decode($v['content']);
			// 获取二级评论
			$child=$this->getTree(array(),$v);
			if(!empty($child)){
				// 按评论时间asc排序
				uasort($child,'comment_sort');
				foreach ($child as $m => $n) {
					// 获取被评论人id
					$reply_user_id=$this->getFieldByCmtid($n['pid'],'ouid');
					// 获取被评论人昵称
					$child[$m]['reply_name']=D('OauthUser')->getFieldById($reply_user_id,'nickname');
				}
			}
			$data[$k]['child']=$child;
		}
		return $data;
	}
	// 递归获取树状结构
	public function getTree($tree,$data){
		$child=$this
			->field('c.*,ou.nickname,ou.head_img')
			->alias('c')
			->join('__OAUTH_USER__ ou ON c.ouid=ou.id')
			->where(array('pid'=>$data['cmtid']))
			->select();
		if(empty($child)){
			return $tree;
		}else{
			foreach ($child as $k => $v) {
				$v['content']=htmlspecialchars_decode($v['content']);
				$tree[]=$v;
			}
			foreach ($child as $k => $v) {
				return $this->getTree($tree,$v);
			}
		}

	}



}

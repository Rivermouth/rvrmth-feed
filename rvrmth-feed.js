jQuery(document).ready(function($) {
	var feedInstanceWrappers = document.getElementsByClassName("rvrmth-feed");
	
	for (var i = 0, l = feedInstanceWrappers.length; i < l; ++i) {
		new FeedInstace(feedInstanceWrappers[i]);
	}
	
	function FeedInstace(wrapper) {
		var ajaxEnabled = _isAjaxEnabled();
		var args = JSON.parse(wrapper.getAttribute("data-args"));
		var postId = JSON.parse(wrapper.getAttribute("data-post-id"));
		var feed = wrapper.querySelector(".feed");
		
		function _isAjaxEnabled() {
			var ajaxEnabled = wrapper.getAttribute("data-ajax-enabled");
			return ajaxEnabled != null && ajaxEnabled.trim().length > 0 && ajaxEnabled != "false";
		}
	
		function shufflePosts() {
			jQuery.post(ajax_object.ajax_url, {
				"action": ajax_object.fetch_items_fn_name,
				"args": args,
				"post_id": postId
			}, function(response) {
				var newFeedElement = $(response);
				newFeedElement.addClass("inserting");
				newFeedElement.insertBefore(feed);
				$(feed).addClass("hiding");
				setTimeout(function() {
					$(feed).remove();
					newFeedElement.removeClass("inserting");
					feed = newFeedElement;
				}, Math.min(1000, args.shuffle_posts_every_ms - 200))
			});
		}
		
		function shufflePostsEvery(timeInMillis) {
			setTimeout(function() {
				shufflePosts();
				shufflePostsEvery(timeInMillis);
			}, timeInMillis);
		}

		if (ajaxEnabled) {
			shufflePostsEvery(args.shuffle_posts_every_ms);
		}
	}
});

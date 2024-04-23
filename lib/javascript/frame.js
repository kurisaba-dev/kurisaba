var _OWNDIR = '';

function buildChan(chan) {
	var htm =
	'<div class="chan">\
		<div class="chan-header toggler" data-toggle="chan_'+chan.id+'">'+chan.name+'</div>\
		<div class="chan-overlay">' +
			((chan.cats || chan.boards) ? ('<a target="main" class="ibtn icon-home" href="'+chan.url+'/"></a>') : '') +
			'<a target="main" class="ibtn icon-info" href="info.html?chan='+chan.id+'"></a>\
		</div>';
	htm += buildBoards(chan);
	return htm;
}

function buildBoards(chan, nohide) {
	var htm = '';
	if(typeof chan.boardLink === 'undefined') chan.boardLink = '/';
	if(!chan.cats && chan.boards) chan.cats = [{
			name: null,
			boards: chan.boards
		}];
	else if(chan.cats && chan.cats.length < 2) chan.cats = [{
			name: null,
			boards: chan.cats[0].boards
		}];

	nohide = ((typeof nohide !== undefined) && nohide);

	if(chan.cats) {
		var open = (nohide || (settings.open == 'alle') || in_array(chan.id, settings.open)) ? '' : 'style="display:none"';
		htm += '<div class="boards" id="chan_'+chan.id+'" '+open+'>';
		iter(chan.cats, function(cat) {
			htm += buildCat(chan, cat);
		})
		htm += '</div></div>';
	}
	else {
		htm += '<div class="boards" style="display:none" data-empty="true" data-url="'+chan.url+'" id="chan_'+chan.id+'"></div></div>'
	}
	return htm;
}

function buildCat(chan, cat, re) {
	var htm = '';
	var _id = cat.is20 ? 'id="cat_'+chan.id+'_20"' : '';
	if(typeof cat.re !== 'undefined' && cat.re) $('#cat_'+chan.id+'_20').remove();
	if(cat.name && chan.cats.length !== 1) htm += '<div class="category" '+_id+'><div class="cat-header">'+cat.name+'</div>';
	if(cat.boards && cat.boards instanceof Array && cat.boards.length) {
		iter(cat.boards, function(board) {
			// KOBATO IS A WHORE
			board.desc = escapeHTML(board.desc);
			var name = ((settings.showDirs && !board.external) ? '/'+board.dir+'/ - ' : '') + board.desc;
			var anonUrl = ((chan.id !== 'own') ? settings.anonym : '') + (board.external ? '' : chan.url);
			var dataDir = !board.external ? 'data-dir="'+board.dir+'"' : '';
			var dataURL = 'data-url="'+ (!board.external ? chan.url+chan.boardLink+board.dir : board.url) +'"';
			var ext = board.external ? 'external' : '';
			var href = board.external ? anonUrl+board.url : anonUrl+chan.boardLink+board.dir;
			htm += '<a target="main" '+dataDir+' data-desc="'+board.desc+'" '+dataDir+' '+dataURL+' class="board '+ext+'" href="'+href+'">'+name+'</a>';
		})
		htm += '</div>';
	}
	else htm = '';
	return htm;
}

var settings = {
	//default settings
	showDirs: false, anonym: '', style: 'Original',
	fetch: function() {
		if(localStorage['showDirs'] == 'true') this.showDirs = localStorage['showDirs'];
		$('#showdirs').prop('checked', this.showDirs);
		this.anonym = (localStorage['anonym'] == 'true') ? 'http://anonym.to?' : '';
		$('#useanonym').prop('checked', this.anonym == 'http://anonym.to?');
		// add style...
		this.open = (typeof localStorage['openChans'] !== 'undefined') ? localStorage['openChans'].split('|') : 'alle';
	},
	change: function() {
		//add code here
	},
	openChans: function() {
		var chans = [];
		var visible = $('.boards:visible');
		visible.each(function(index) {
			chans.push($(this).attr('id').split('chan_')[1]);
		});
		localStorage.setItem('openChans', chans.join('|'));
	}
}

function rebuildChans() {
	var htm = '';
	if(in_array('own', settings.open)) $('#chan_own').show();
	getOwnBoardlist(function(cats) {
		$('#chan_own').append(
			buildBoards({
				id: 'own',
				name: _SITENAME,
				url: _OWNDIR,
				cats: cats
			}, true)
		);
	});
	iter(chans, function(chan) {
		htm += buildChan(chan);
	});
	$('#content').html(htm);
}

function getOwnBoardlist(callback) {
	if(typeof _ownboards !== 'undefined') return callback(_ownboards);

	console.log('no _ownboards found, will try to fetch some');
	//get specially constructed board list (menu.tpl)
	$.get('menu.html')
	.done(function(data) {
			//remove trailing ","
			data = data.replace(/,\s+\]/mg, "]");
			try {
				cats = JSON.parse(data.replace(/,\s+\]/mg, "]"));
				// if(cats.length < 2) cats[0].name = null;
				callback(cats);
			}
			catch(err) {
				console.log('Error JSON.parsing boardlist: ', err)
				callback(null);
			}
		}
	)
	.fail(function(err) {
		console.log('Error $.getting boardlist: ', err)
		callback(null);
	});
}

function readyset() {
	parent.allReady();
	settings.fetch();
	rebuildChans();
	$('body').on('click', '.toggler', function() {
		var $to = $('#'+$(this).data('toggle'));
		//special case
		if($to.data('empty')) {
			parent.frames['main'].location.href = $to.data('url');
			return;
		}
		if($to.is(':visible')) $to.slideUp(settings.openChans);
		else $to.slideDown(settings.openChans);
	})
	$('body').mCustomScrollbar({theme:"minimal-dark", scrollInertia: 200});
	$('#showdirs').change(function() {
		if(settings.showDirs == $(this).is(':checked')) return;
		settings.showDirs = $(this).is(':checked');
		localStorage.setItem('showDirs', settings.showDirs);
		$('.board:not(.external)').each(function() {
			var dir = settings.showDirs ? '/'+$(this).data('dir')+'/ - ' : '';
			$(this).text(dir + $(this).data('desc'))
		});
	});
	$('#useanonym').change(function() {
		if((settings.anonym == 'http://anonym.to?') == $(this).is(':checked')) return;
		settings.anonym = $(this).is(':checked') ? 'http://anonym.to?' : '';
		localStorage.setItem('anonym', $(this).is(':checked'));
		$('.boards:not(#chan_own) .board').each(function() {
			$(this).attr('href', settings.anonym + $(this).data('url'));
		});
	});
	/*if(settings.showDirs) {
		$('#ownboard').text('/meta/ - '+$('#ownboard').text())
	}*/
	$('body').on('click', '.board', function() {
		$('.boardover').removeClass('boardover')
		$('.onchan').removeClass('onchan')
		$(this).addClass('boardover').parents('.chan').addClass('onchan');
		if(parent._frames.layout == 'horizontal') menu.close();
	})
	$('body').on('click', '.icon-home', function() {
		$('.boardover').removeClass('boardover')
		$('.onchan').removeClass('onchan')
	});
	$('#showhide').click(menu.toggle);
	$('#showhide').on('mouseenter', function() {
		if(parent._frames.layout == 'vertical' && in_array(parent._frames.behavior, ['overlay', 'shift'])) menu.open();
	});
	$('#pinunpin').click(parent.toggleBehavior);
}

function iter(array,callback){if(typeof array!=='undefined'&&array){if(typeof array.length==='undefined')return callback(array);var i=0,len=array.length;for(;i<len;i++){callback(array[i]);}}}
function in_array(needle,haystack){if(typeof haystack!=='object'){if(needle===haystack)return true;else return false;}
for(var key in haystack){if(needle===haystack[key]){return true;}}
return false;}

if (typeof (is_menu_frame) == "undefined") var stylez = {
	current: preferredTheme,
	change: function() {
		iter(document.getElementsByTagName("link"), function(sheet) {
			if(sheet.getAttribute("rel").indexOf("style") != -1 && sheet.getAttribute("title")) {
				if(!sheet.disabled) sheet.disabled = true;
				else {
					sheet.disabled = false;
					stylez.current = sheet.getAttribute("title");
					try {
						parent.frames['main'].Styles.change(capitalize(stylez.current));
					}
					catch(e) {}
					/*$('.themeswitch').removeClass('hbs-sel');
					$('#'+stylez.current+'-theme').addClass('hbs-sel');*/
				}
			}
		});
		localStorage.setItem('theme', stylez.current);
		parent.changeBG();
	}
}

function capitalize(str) {
	return str.charAt(0).toUpperCase() + str.slice(1);
}

var menu = {
	open: function() {
		parent.openMenu();
		$("body").mCustomScrollbar("update");
		$('#showhide').removeClass('menu-closed');
	},
	close: function() {
		parent.closeMenu();
		$("body").mCustomScrollbar("disable",true);
		$('#showhide').addClass('menu-closed');
	},
	toggle: function() {
		if(parent._open) menu.close();
		else menu.open();
	}
}

var router = {
	escRx: function(s) {
	    return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
	},
	currentHash: '',
	determineIdentity: function(url) {
		var guro, result = null;
		if (typeof(chans) != "undefined")
		{
			iter(chans, function(chan) {
			if(typeof chan.rx === 'undefined')
				chan.rx = new RegExp('('+router.escRx(chan.url)+')(?:'+router.escRx(chan.boardLink)+'([a-zA-Z0-9_\.]+))?');
			guro = chan.rx.exec(url);
			if(guro !== null) result = {
				chan: chan.id,
				board: guro[2] || null
			};
		}); }
		return result;
	},
	indicateOn: function(chid, board) {
		var $chan = $('#chan_'+chid).parent('.chan');
		var title = '';
		if($chan.length) {
			$('.onchan').removeClass('onchan');
			$chan.addClass('onchan');
			//Update title
			title += $chan.find('.chan-header').text();
		}
		if(!(typeof board === 'undefined' || !board)) {
			var $brd = $chan.find('a[data-dir="'+board+'"]');
			if($brd.length) {
				$('.boardover').removeClass('boardover');
				$brd.addClass('boardover');
				//update title
				title += ':'+$brd.data('desc');
			}
		}
		//title
		if(title === '') title = _SITENAME;
		else title = title + (' • '+_SITENAME);
		parent.document.title = title;
	},
	sync: function(event) {
		//prevent follow
		router.noFollow = event.data;
		// set the hash
		parent.history.replaceState(null, null, '#/'+event.data);
		// define chan
		var identity = router.determineIdentity(event.data);
		if(identity) {
			router.indicateOn(identity.chan, identity.board)
		}
		else parent.document.title = _SITENAME;
	},
	follow: function() {
		if(router.noFollow && (router.noFollow.split(/#\/?/)[1] === parent.location.hash)) {
			router.noFollow = false;
			return
		}
		else
		if(parent.location.hash) parent.frames['main'].location.href = parent.location.hash.split(/#\/?/)[1];
	},
	clearHash: function() {
		parent.document.title = _SITENAME;
		parent.history.replaceState({}, parent.document.title, "/");
	},
	noFollow: false
}

window.addEventListener("message", router.sync, false);

// KOBATO IS A WHORE
function escapeHTML(html) {
	if(typeof html !== 'string') return 'null';
    var fn=function(tag) {
        var charsToReplace = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&#34;'
        };
        return charsToReplace[tag] || tag;
    }
    return html.replace(/[&<>"]/g, fn);
}

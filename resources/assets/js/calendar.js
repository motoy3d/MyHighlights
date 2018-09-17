// forked from takahiro.nakamori's "JavaScriptでカレンダー" http://jsdo.it/takahiro.nakamori/tlAF
(function(){
/* ========== 休みを定義 ========== */
var holiday = [];
/* 休みの設定方法
holiday[年] = [];
holiday[年][月] = [休みの日,休みの日,休みの日,休みの日,休みの日,休みの日];
「年」「月」「休みの日」は、半角英数字で入力してください。
*/
// TODO 何年分？
holiday[2016] = [];
holiday[2016][8] = [1,2,3,4,5,11,12,13];

for(var i=2016;i<=2020;i++){
    holiday[i]=[];
    for(var k=1;k<=12;k++){
    	holiday[i][k]=[];
    }
}
var selectedDate = "16";
	
/* ========== 以下、カレンダー生成部分========== */
var yobi_ja = ['日', '月', '火', '水', '木', '金', '土'];

function TnCalendar(parent){
  if (typeof parent === 'string') {
    parent = $('#' + parent);
  }
  this.parent = parent;
}
    
window.TnCalendar = TnCalendar;

TnCalendar.prototype = {
  create: create,
  update: update,
  remove: remove,
  set_caption: set_caption,
  set_body: set_body,
  set_date: set_date,
  onclick_date: onclick_date,
  onclick_month: onclick_month
};

function onclick_date(id, year, month, date){
  return false;
}

function onclick_month(id, year, month){
  this.update(+year, +month);
  return false;
}
    
function remove(){
  this.parent.remove(this.table);
}
    
function update(year, month){
  this.remove();
  this.create(year, month);
}
    
function set_date(year, month){
  var today = new Date();
  this.month = parseInt(month, 10)|| (today.getMonth()+1);
  this.year = parseInt(year, 10) || today.getFullYear();
}
    
function set_caption(year, month){
  var today = new Date();
  var this_month = today.getMonth()+1;
  var this_year = today.getFullYear();
  
  var caption = $('caption');
  var div = $('div');
  var next = $('a', {
    href: '#month-' + ((month == 12) ? year+1 : year)+ '-' + (month==12?1:month+1),
    class: 'next',
    html: (month==12?1:month+1) + '月 <i class="fa fa-caret-right"></i>'
  });

  $('#currentYearMonth').text(year + '年' + month + '月');

//   if(year != this_year || month != this_month){
  var prev = $('a', {
    href: '#month-' + ((month == 1) ? year-1 : year) + '-' + (month==1?12:month-1),
    class: 'prev',
    html: '<i class="fa fa-caret-left"></i> ' + (month==1?12:month-1) + '月'
  });
  div.append(prev);
//   }
  
  div.append(next);
  caption.append(div);
  this.table.append(caption);
}

function set_body(year, month){
  var tbody = $('tbody');
  var first = new Date(year, month - 1, 1);
  var last = new Date(year, month, 0);
  var first_day = first.getDay();
  var last_date = last.getDate();
  var date = 1;
  var skip = true;
  for (var row = 0; row < 7; row++) {
    var tr = $('tr');
    for (var col = 0; col < 7; col++){
      if (row === 0){
        var day = yobi_ja[col];
        // th.appendChild(document.createTextNode(day));
        // th.className = 'calendar day-head day' + col;
        // tr.appendChild(th);
        var th = $('th', {
          text: day,
          class: 'calendar day-head day' + col
        });
        tr.append(th);
      } else {
        if (row === 1 && first_day === col){
          skip = false;
        }
        if (date > last_date) {
          skip = true;
        }
				var selectedClass = "";
				if (date == selectedDate) {
					selectedClass = " selectedDate";
				}
        var td = $('td', {
          class: 'calendar day' + col + selectedClass
        });
				if (!skip) {
          td.text(date);
//TODO サンプル
					if(col==6) {
						td.append($('br'));
						var scheduleP = $('span', {text: '10:00練習@多摩川グラ…'});
						td.append(scheduleP);
					}
    		  for(var i =0; i < holiday[year][month].length; i++){
		    	  if(holiday[year][month][i] == date){
			        td.addClass('holiday');
			      }
		      }
          date++;
        } else {
          td.html('<span class="blank">&nbsp;</span>');
        }
        tr.append(td);
      }
    }
    tbody.append(tr);
  }
  this.table.append(tbody);
}
    
function create(year, month){
  var that = this;
  var table = $('table');
  table.attr('class', 'calendar-table');
  this.table = table;
  //TODO jquery用に書き換え
  table.onclick = function(e){
    var evt = e || window.event; 
    var target = evt.target || evt.srcElement;
    return that.onclick_month.apply(that,target.hash.match(/month-(\d+)-(\d+)/));
  };
  this.set_date(year, month);
  this.set_caption(this.year, this.month);
  this.set_body(this.year, this.month);
  this.parent.append(table);
}
})();

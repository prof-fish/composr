{$REQUIRE_CSS,menu__embossed}

{+START,IF_NON_EMPTY,{CONTENT}}
	{$SET,menu_id,r_{MENU|}}
	<nav class="menu_type__embossed" data-view-core-menus="Menu" data-view-args="{+START,PARAMS_JSON,MENU,JAVASCRIPT_HIGHLIGHTING,menu_id}{_*}{+END}">
		<ul class="nl" id="{$GET,menu_id}">
			{CONTENT}
		</ul>
	</nav>
{+END}
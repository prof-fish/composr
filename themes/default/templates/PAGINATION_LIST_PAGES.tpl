<form title="{!COUNT_PAGES}" class="inline" action="{$URL_FOR_GET_FORM*,{URL}}" method="get" target="_self">
	{$SET,RAND_PAGINATION_LIST_PAGES,{$RAND}}

	<div class="pagination_pages">
		{HIDDEN}
		<div class="accessibility_hidden"><label for="blp_start{$GET*,RAND_PAGINATION_LIST_PAGES}">{!COUNT_PAGES}: {$GET*,TEXT_ID}</label></div>
		<select{+START,IF,{$JS_ON}} onchange="/*guarded*/this.form.submit();"{+END} id="blp_start{$GET*,RAND_PAGINATION_LIST_PAGES}" name="{START_NAME*}">
			{LIST}
		</select>{+START,IF,{$NOT,{$JS_ON}}}<input onclick="disable_button_just_clicked(this);" class="button_micro buttons__morepage" type="submit" value="{!JUMP}: {$GET*,TEXT_ID}" />{+END}
	</div>
</form>


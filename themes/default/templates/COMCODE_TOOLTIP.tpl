<a title="{$STRIP_TAGS,{TOOLTIP}} {!LINK_NEW_WINDOW}" target="_blank" class="non_link"{+START,IF_NON_EMPTY,{URL}} href="{URL*}"{+END}{+START,IF_EMPTY,{URL}} href="#" onclick="return false;"{+END}><span class="comcode_concept_inline" onmouseover="if (typeof window.activate_tooltip!='undefined') activate_tooltip(this,event,'{TOOLTIP;^*}','700px');">{CONTENT}</span></a>

{$REQUIRE_JAVASCRIPT,core_permission_management}
<tr class="{$CYCLE,zebra,zebra_0,zebra_1}" data-tpl="permissionKeysPermissionRow">
	<td>
		<label class="accessibility_hidden" for="key_{UID*}">{!MATCH_KEY}</label>
		<div class="constrain_field">
			<input class="wide_field" maxlength="255" type="text" id="key_{UID*}" name="key_{UID*}" value="{KEY*}" />
		</div>
	</td>
	{CELLS}
	<td>
		<input class="button_micro js-click-btn-toggle-value" type="button" value="{+START,IF,{ALL_OFF}}+{+END}{+START,IF,{$NOT,{ALL_OFF}}}-{+END}" data-click-eval="{CODE*}" />
	</td>
</tr>

{strip}
	<div class="row form-group">
		<div class="col-sm-10 col-xs-10">
			<div class="row" style="margin-bottom: 10px;">
				<div class="col-sm-3 col-xs-3">URL :<span class="redColor">*</span></div>
				<div class="col-sm-8 col-xs-8">
					<input type="text" name="url" class="inputElement" style="width:100%"
						value="{$TASK_OBJECT->url}" placeholder="https://..." data-rule-required="true" />
				</div>
			</div>
			<div class="row" style="margin-bottom: 10px;">
				<div class="col-sm-3 col-xs-3">Método :</div>
				<div class="col-sm-4 col-xs-4">
					<select name="method" class="select2">
						<option {if $TASK_OBJECT->method eq 'POST' || empty($TASK_OBJECT->method)}selected{/if} value="POST">POST</option>
						<option {if $TASK_OBJECT->method eq 'GET'}selected{/if} value="GET">GET</option>
						<option {if $TASK_OBJECT->method eq 'PUT'}selected{/if} value="PUT">PUT</option>
						<option {if $TASK_OBJECT->method eq 'PATCH'}selected{/if} value="PATCH">PATCH</option>
					</select>
				</div>
			</div>
			<div class="row" style="margin-bottom: 10px;">
				<div class="col-sm-3 col-xs-3">Headers :</div>
				<div class="col-sm-8 col-xs-8">
					<textarea name="headers" class="inputElement" rows="3" style="width:100%;font-family:monospace;font-size:12px;"
						placeholder="Authorization: Bearer TOKEN&#10;X-Custom: valor">{$TASK_OBJECT->headers}</textarea>
					<small class="text-muted">Um header por linha no formato <code>Nome: Valor</code></small>
				</div>
			</div>
			<div class="row" style="margin-bottom: 10px;">
				<div class="col-sm-3 col-xs-3">Body (JSON) :</div>
				<div class="col-sm-8 col-xs-8">
					<textarea name="body" class="inputElement" rows="6" style="width:100%;font-family:monospace;font-size:12px;"
						placeholder="{&quot;campo&quot;: &quot;$(fieldname)&quot;}">{$TASK_OBJECT->body}</textarea>
					<small class="text-muted">Use <code>$(nome_do_campo)</code> para inserir valores do registro</small>
				</div>
			</div>
		</div>
	</div>
{/strip}

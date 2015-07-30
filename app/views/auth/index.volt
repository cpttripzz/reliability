<form method='post' action='auth/index'>
    <fieldset>
        <div>
            <label for='username'>Username/Email</label>
            <div>
                {{ text_field('username') }}
            </div>
        </div>
        <div>
            <label for='password'>Password</label>
            <div>
                {{ password_field('password') }}
            </div>
        </div>
        <div>
            {{ submit_button('Login') }}
        </div>
    </fieldset>
</form>
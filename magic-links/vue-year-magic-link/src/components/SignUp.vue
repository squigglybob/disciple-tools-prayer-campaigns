<template>
  <button @click="show_form = !show_form">Sign Up To Pray Everyday</button>
  <form v-if="show_form" class="form" @submit.prevent="submit">
    <label>
      Name
      <input v-model="name" placeholder="John Doe">
    </label>
    <label>
      Email
      <input type="email" v-model="email" placeholder="awesowe@email.com">
    </label>
    <button type="submit">Submit</button>
  </form>

</template>

<script>
import axios from "axios";

const settings = window.campaign_objects

export default {
  components: {
  },
  name: 'SignUp',
  data(){
    return {
      show_form: false,
      name: '',
      email: '',
      settings
    }
  },
  methods: {
    submit() {
      axios
      .post( `${settings.root}${settings.parts.root}/v1/${settings.parts.type}`, {
        email: this.email,
        name: this.name,
        parts:settings.parts
      } )
      .then(response => {
        console.log(response.data)
        this.email = ""
        this.name = ""
        this.show_form = false
      }
    )
    }
  },
  mounted () {
  }
}

</script>


<style scoped>
  .form {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1em;
    background-color: rgba(121,141,179,0.35);
    border-radius: 10px;
  }
  label {
    margin-bottom: 1em;
  }
</style>

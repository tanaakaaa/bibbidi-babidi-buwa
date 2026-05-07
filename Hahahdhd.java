public class Clinica{

public static void main(String[] args) {



    Medico m1 = new Medico("Carlos", 45, "Cardiologia", "12345");

    Paciente p1 = new Paciente("Ana", 30, "Hipertensao");

    Consulta c1 = new Consulta(m1, p1, "17/04/2026");

    

    Medico m2 = new Medico("Roberto", 54, "Ortopedia", "54321");

    Paciente p2 = new Paciente("Fernanda", 21, "Fratura no tornozelo");

    Consulta c2 = new Consulta(m2, p2, "22/04/2026");



    System.out.println(c1);

    System.out.println(c2);

}

}

class Pessoa {

private String nome;

private int idade;



public Pessoa(String nome, int idade) {

    this.nome = nome;

    this.idade = idade;

}



public String getNome() {

    return nome;

}



public int getIdade() {

    return idade;

}



@Override

public String toString() {

    return nome + " (" + idade + " anos)";

}

}

class Medico extends Pessoa {

private String especialidade;

private String crm;



public Medico(String nome, int idade, String especialidade, String crm) {

    super(nome, idade);

    this.especialidade = especialidade;

    this.crm = crm;

}



public String getEspecialidade() {

    return especialidade;

}



@Override

public String toString() {

    return "Dr(a). " + super.toString() + " - " + especialidade + " (CRM: " + crm + ")";

}

}

class Paciente extends Pessoa {

private String enfermidade;



public Paciente(String nome, int idade, String enfermidade) {

    super(nome, idade);

    this.enfermidade = enfermidade;

}



public String getEnfermidade() {

    return enfermidade;

}



@Override

public String toString() {

    return "Paciente: " + super.toString() + " - Enfermidade: " + enfermidade;

}

}

class Consulta {

private Medico medico;

private Paciente paciente;

private String data;



public Consulta(Medico medico, Paciente paciente, String data) {

    this.medico = medico;

    this.paciente = paciente;

    this.data = data;

}



@Override

public String toString() {

    return "Consulta em " + data + "\n" + medico.toString()+ "\n" + paciente.toString() + "\n";

}

}

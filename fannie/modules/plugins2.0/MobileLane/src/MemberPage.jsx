
import React from 'react';
import ReactDOM from 'react-dom';
import {
    FormGroup,
    FormControl,
    InputGroup,
    ControlLabel,
    Button,
    Alert
} from 'react-bootstrap';
var $ = require('jquery');

export default class MemberPage extends React.Component {
    constructor(props) {
        super(props);
        this.state = { search: "", results: [], noResults: false }
    }

    componentDidMount() {
        ReactDOM.findDOMNode(this.refs.mainInput).focus();
    }

    setMember(card, person) {
        $.ajax({
            url: 'api/member/',
            method: 'post',
            data: JSON.stringify({cardNo: card, personNum: person, e: this.props.empNo, r: this.props.registerNo})
        }).done(resp => {
            this.props.mem(card);
            this.props.nav('tender');
        });
    }

    search(ev) {
        ev.preventDefault();
        $.ajax({
            url: 'api/member/',
            method: 'get',
            data: 'term='+this.state.search
        }).done(resp => {
            var empty = resp.members.length == 0 ? true : false;
            this.setState({results: resp.members, noResults: empty});
        });
    }

    render() {
        return (
            <form onsubmit={this.search.bind(this)}>
                {results.map(i => {
                    <p>
                        <a className="h3" 
                            onClick={() => this.setMember(i.cardNo, i.personNum)}>
                            {i.cardNo} {i.name}
                        </a>
                    </p>
                })}
                {noResults ? <Alert bsStyle="danger">No matches</Alert> : null} 
                <FormGroup>
                    <ControlLabel>Member # or name</ControlLabel>
                    <FormControl type="text" ref="mainInput"
                        placeholder="Enter last name or member number"
                        value={this.state.search}
                        onChange={(e) => this.setState({amount: e.target.value})} />
                </FormGroup>
                <FormGroup>
                    <Button bsStyle="success" block={true} type="submit">Search</Button>
                </FormGroup>
                <FormGroup>
                    <Button block={true} onClick={() => this.props.nav('items')}>Go Back </Button>
                </FormGroup>
            </form>
        );
    }
}


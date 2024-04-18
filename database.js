import express from 'express';
import bodyParser from 'body-parser';
import mysql from 'mysql2/promise';
import cors from 'cors';
import nodemailer from 'nodemailer';
import fs from 'fs';
import dotenv from 'dotenv';

const app = express();
app.use(cors()); // Enable CORS for all routes
app.use(bodyParser.json());

// Initialize configuration from .env file
dotenv.config();

// Your MySQL SSL configuration using environment variables
const sslOptions = {
    rejectUnauthorized: true,
    ca: fs.readFileSync(process.env.CA_CERT_PATH).toString() // Load CA certificate using path from environment variable
};

// Create the connection pool with SSL options and environment variables
const pool = mysql.createPool({
    host: process.env.DB_HOST,
    port: process.env.DB_PORT,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
    ssl: sslOptions
});


const emailAddresses = ['aaron@acemovers.com.au', 'harry@acemovers.com.au', 'kevin@acemovers.com.au', 'nick@acemovers.com.au'];

const transporter = nodemailer.createTransport({
    host: "smtp.elasticemail.com",
    port: 587,
    secure: false,
    auth: {
        user: 'aaron@acemovers.com.au',
        pass: '8F1E23DEE343B60A0336456A6944E7B4F7DA',
    },
});

// Function to send email
const sendEmail = async (formData, nextEmailAddress) => {
    let emailBody = `Name: ${formData.Name}\nBedrooms: ${formData.Bedrooms}\nPickup: ${formData.Pickup}\nDropoff: ${formData.Dropoff}\nDate: ${formData.Date}\nPhone number: ${formData.Phone}\nEmail: ${formData.Email}\nDetails: ${formData.Details}`;

    let mailOptions = {
        from: 'aaron@acemovers.com.au',
        to: nextEmailAddress,
        subject: "New Lead",
        text: emailBody,
    };

    try {
        await transporter.sendMail(mailOptions);
        console.log('Email sent to ' + nextEmailAddress);
    } catch (error) {
        console.error('Error sending email: ', error);
        throw error; // Re-throw the error to be caught by the caller
    }
};

// Function to get the next email index and count
const getNextEmailInfo = async () => {
    const [rows] = await pool.query('SELECT current_index, count FROM email_tracker WHERE id = 1');
    return rows[0];
};

// Function to update the email index and count
const updateEmailInfo = async (index, count) => {
    await pool.query('UPDATE email_tracker SET current_index = ?, count = ? WHERE id = 1', [index, count]);
};

// End point for form submitting to database
app.post('/submit-form', async (req, res) => {
    const { Name, Bedrooms, Pickup, Dropoff, Date, Phone, Email, Details } = req.body;
    const values = [Name || null, Bedrooms || null, Pickup || null, Dropoff || null, Date || null, Phone || null, Email || null, Details || null];
    const query = `INSERT INTO leads (lead_name, bedrooms, pickup, dropoff, lead_date, phone, email, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?);`;

    try {
        await pool.query(query, values);

        const { current_index, count } = await getNextEmailInfo();

        await sendEmail(req.body, emailAddresses[current_index]);

        const newCount = count + 1;
        const maxEmailsPerAddress = 3; // This can be adjusted as needed
        let newIndex = current_index;

        if (newCount >= maxEmailsPerAddress) {
            newIndex = (current_index + 1) % emailAddresses.length;
            await updateEmailInfo(newIndex, 0); // Reset count for the new index
        } else {
            await updateEmailInfo(current_index, newCount); // Increment count for the current index
        }

        res.send('Form data saved and email sent successfully!');
    } catch (error) {
        console.error('Error processing form submission and sending email: ', error);
        res.status(500).send('Error processing request');
    }
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});


// // A whitelist of valid sort fields to prevent SQL injection
// const validSortFields = new Set([
//     'lead_id', 'lead_name', 'bedrooms', 'pickup', 'dropoff', 'lead_date', 'phone', 'email', 'details', 'created_at'
// ]);



// app.patch('/update-booking-status', async (req, res) => {
//     const { id, bookingStatus } = req.body;
//     const query = `UPDATE leads SET booking_status = ? WHERE lead_id = ?;`;

//     try {
//         const [result] = await pool.query(query, [bookingStatus, id]);
//         if (result.affectedRows > 0) {
//             res.send('Booking status updated successfully.');
//         } else {
//             res.status(404).send('Record not found.');
//         }
//     } catch (error) {
//         console.error('Error updating booking status: ', error);
//         res.status(500).send('Error updating booking status');
//     }
// });